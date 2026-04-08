<?php
session_start();
require_once './config.php';


// Set a custom error handler to return JSON for errors
set_exception_handler(function ($exception) {
    $response = array(
        "success" => false,
        "message" => $exception->getMessage()
    );
    echo json_encode($response);
    exit();
});


// Receive the action type
$action = $_GET['action'] ?? '';

// Check if the 'action' parameter is set in the URL
if ($action == '') {
    $response = array(
        "success" => false,
        "message" => "No action specified!"
    );
    echo json_encode($response);
    exit();
}



//////////////////////////////////////////////////////////////////////////////////////
//////////////////////////// Handle the 'add-user' action ///////////////////////////
////////////////////////////////////////////////////////////////////////////////////
if ($action == 'add-user') {
    // Post data from the request
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? ''; // optional
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($gender) || empty($password)) {
        $response = array(
            "success" => false,
            "message" => "Fill up the required fields."
        );
        echo json_encode($response);
        exit();
    }

    // Check if user already exists by phone
    $stmt = $conn->prepare("SELECT user_id FROM user_info WHERE user_phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $response = array(
            "success" => false,
            "message" => "A user with this phone already exists."
        );
        echo json_encode($response);
        exit();
    }

    // Check if email exists (if provided)
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT user_id FROM user_info WHERE user_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $response = array(
                "success" => false,
                "message" => "A user with this email already exists."
            );
            echo json_encode($response);
            exit();
        }
    }

    // Check if the password and confirm password match
    if ($password !== $confirm_password) {
        $response = array(
            "success" => false,
            "message" => "Password and Confirm Password do not match."
        );
        echo json_encode($response);
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare and execute the SQL statement to insert the user into the database
    $stmt = $conn->prepare("INSERT INTO user_info (user_fName, user_lName, user_phone, user_email, user_gender, user_password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $first_name, $last_name, $phone, $email, $gender, $hashed_password);

    if ($stmt->execute()) {
        $response = array(
            "success" => true,
            "message" => "User successfully added"
        );
    } else {
        $response = array(
            "success" => false,
            "message" => "Error: " . $stmt->error
        );
    }

    echo json_encode($response);
    exit();
}
////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////// END ///////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////



//////////////////////////////////////////////////////////////////////////////////
///////////////////// Handle the 'update-user-info' action //////////////////////
////////////////////////////////////////////////////////////////////////////////
else if ($action == 'update-user-info') {

    $user_id = $_POST['user_id'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $gender = $_POST['gender'] ?? '';

    // Validate required fields
    if (empty($user_id) || empty($first_name) || empty($last_name) || empty($phone) || empty($gender)) {
        $response = array(
            "success" => false,
            "message" => "All fields are required."
        );
        echo json_encode($response);
        exit();
    }

    // Check if phone is unique (exclude current user)
    $stmt = $conn->prepare("SELECT user_id FROM user_info WHERE user_phone = ? AND user_id != ?");
    $stmt->bind_param("si", $phone, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $response = array(
            "success" => false,
            "message" => "This phone number is already in use by another user."
        );
        echo json_encode($response);
        exit();
    }

    // Check if email is unique (exclude current user)
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT user_id FROM user_info WHERE user_email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $response = array(
                "success" => false,
                "message" => "This email is already in use by another user."
            );
            echo json_encode($response);
            exit();
        }
    }

    // Prepare and execute the SQL statement to update the user
    $stmt = $conn->prepare("UPDATE user_info 
                           SET user_fName = ?, user_lName = ?, user_phone = ?, user_email = ?, user_gender = ? 
                           WHERE user_id = ?");
    $stmt->bind_param("sssssi", $first_name, $last_name, $phone, $email, $gender, $user_id);

    if ($stmt->execute()) {
        $response = array(
            "success" => true,
            "message" => "User successfully updated"
        );
    } else {
        $response = array(
            "success" => false,
            "message" => "Error: " . $stmt->error
        );
    }

    echo json_encode($response);
    exit();
}
////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////// END ///////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////



//////////////////////////////////////////////////////////////////////////////////
//////////////////////// Handle the 'update-user-password' action ////////////////////////
////////////////////////////////////////////////////////////////////////////////
else if ($action == 'update-user-password') {
    
    $user_id = $_POST['user_id'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Validate required fields
    if (empty($user_id) || empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
        echo json_encode([
            "success" => false,
            "message" => "All fields are required."
        ]);
        exit();
    }

    // Confirm new passwords match
    if ($new_password !== $confirm_new_password) {
        echo json_encode([
            "success" => false,
            "message" => "New passwords do not match."
        ]);
        exit();
    }

    // Fetch current password hash from database
    $stmt = $conn->prepare("SELECT user_password FROM user_info WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "User not found."
        ]);
        exit();
    }

    $user = $result->fetch_assoc();

    // Verify current password
    if (!password_verify($current_password, $user['user_password'])) {
        echo json_encode([
            "success" => false,
            "message" => "Current password is incorrect."
        ]);
        exit();
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password in database
    $stmt = $conn->prepare("UPDATE user_info SET user_password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Password updated successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to update password."
        ]);
    }

    exit();
}
////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////// END ///////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////



//////////////////////////////////////////////////////////////////////////////////////
/////////////////////////// Handle the 'user-login' action //////////////////////////
////////////////////////////////////////////////////////////////////////////////////
if ($action == 'user-login') {

    // Get the phone and password from POST data
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($phone) || empty($password)) {
        echo json_encode([
            "success" => false,
            "message" => "You must provide both phone and password."
        ]);
        exit();
    }

    // Fetch user by phone
    $stmt = $conn->prepare("SELECT * FROM user_info WHERE user_phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['user_password'])) {
            // Set session variables
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['user_fName'] . " " . $user['user_lName'];

            $response = [
                "success" => true,
                "message" => "Login successful.",
                "user" => [
                    "id" => $user['user_id'],
                    "first_name" => $user['user_fName'],
                    "last_name" => $user['user_lName'],
                    "phone" => $user['user_phone'],
                    "email" => $user['user_email'],
                    "gender" => $user['user_gender']
                ]
            ];
        } else {
            $response = [
                "success" => false,
                "message" => "Your password is incorrect!"
            ];
        }
    } else {
        $response = [
            "success" => false,
            "message" => "Invalid phone or password."
        ];
    }

    echo json_encode($response);
    exit();
}
////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////// END ///////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////



// Handle wrong/invalid action
else {
    $response = array(
        "success" => false,
        "message" => "Invalid action specified!"
    );
    echo json_encode($response);
    exit();
}

?>