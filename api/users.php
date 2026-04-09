<?php
session_start();
require_once './config.php';

header('Content-Type: application/json');

// Global Exception Handler
set_exception_handler(function ($e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit();
});

// Get action
$action = $_GET['action'] ?? '';

if (!$action) {
    echo json_encode([
        "success" => false,
        "message" => "No action specified!"
    ]);
    exit();
}

//////////////////////////////////////////////////////////////
/////////////////////// ADD USER //////////////////////////////
//////////////////////////////////////////////////////////////
if ($action == 'add-user') {

    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'patient';

    if (empty($full_name) || empty($phone) || empty($password)) {
        echo json_encode([
            "success" => false,
            "message" => "Required fields missing."
        ]);
        exit();
    }

    if ($password !== $confirm_password) {
        echo json_encode([
            "success" => false,
            "message" => "Passwords do not match."
        ]);
        exit();
    }

    // Check phone
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone=?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Phone already exists."
        ]);
        exit();
    }

    // Check email
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode([
                "success" => false,
                "message" => "Email already exists."
            ]);
            exit();
        }
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (full_name, phone, email, password_hashed, user_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $full_name, $phone, $email, $hashed_password, $user_type);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "User created successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => $stmt->error
        ]);
    }

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// UPDATE USER INFO /////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'update-user-info') {

    $id = $_POST['id'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $user_type = $_POST['user_type'] ?? 'patient';

    if (empty($id) || empty($full_name) || empty($phone)) {
        echo json_encode([
            "success" => false,
            "message" => "Required fields missing."
        ]);
        exit();
    }

    // Check phone unique
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone=? AND id!=?");
    $stmt->bind_param("si", $phone, $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "message" => "Phone already used."
        ]);
        exit();
    }

    // Check email unique
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode([
                "success" => false,
                "message" => "Email already used."
            ]);
            exit();
        }
    }

    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, email=?, user_type=? WHERE id=?");
    $stmt->bind_param("ssssi", $full_name, $phone, $email, $user_type, $id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "User updated successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => $stmt->error
        ]);
    }

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// UPDATE PASSWORD //////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'update-user-password') {

    $id = $_POST['id'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$id || !$current_password || !$new_password || !$confirm_password) {
        echo json_encode(["success" => false, "message" => "All fields required"]);
        exit();
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(["success" => false, "message" => "Passwords do not match"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT password_hashed FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit();
    }

    $user = $res->fetch_assoc();

    if (!password_verify($current_password, $user['password_hashed'])) {
        echo json_encode(["success" => false, "message" => "Wrong current password"]);
        exit();
    }

    $newHash = password_hash($new_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password_hashed=? WHERE id=?");
    $stmt->bind_param("si", $newHash, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Password updated"]);
    } else {
        echo json_encode(["success" => false, "message" => "Update failed"]);
    }

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////////// LOGIN ////////////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'user-login') {

    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$phone || !$password) {
        echo json_encode([
            "success" => false,
            "message" => "Phone & password required."
        ]);
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE phone=?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        if (password_verify($password, $user['password_hashed'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];

            echo json_encode([
                "success" => true,
                "message" => "Login successful",
                "user" => $user
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Incorrect password"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);
    }

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// INVALID ACTION ///////////////////////////
//////////////////////////////////////////////////////////////
else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid action"
    ]);
    exit();
}