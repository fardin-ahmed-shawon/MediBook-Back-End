<?php
require_once './config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if (!$action) {
    echo json_encode(["success" => false, "message" => "No action"]);
    exit();
}

//////////////////////////////////////////////////////
//////////////////// ADD //////////////////////////////
//////////////////////////////////////////////////////
if ($action == 'add') {

    $user_id = $_POST['user_id'] ?? '';
    $specialized_area = $_POST['specialized_area'] ?? null;
    $experience = $_POST['years_of_experience'] ?? 0;

    if (!$user_id) {
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }

    // Prevent duplicate doctor for same user
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Doctor already exists"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO doctors (user_id, specialized_area, years_of_experience) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $user_id, $specialized_area, $experience);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Doctor added" : $stmt->error
    ]);
}

//////////////////////////////////////////////////////
//////////////////// EDIT /////////////////////////////
//////////////////////////////////////////////////////
else if ($action == 'edit') {

    $id = $_POST['id'] ?? '';
    $specialized_area = $_POST['specialized_area'] ?? null;
    $experience = $_POST['years_of_experience'] ?? 0;

    if (!$id) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit();
    }

    $stmt = $conn->prepare("UPDATE doctors SET specialized_area=?, years_of_experience=? WHERE id=?");
    $stmt->bind_param("iii", $specialized_area, $experience, $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Updated" : $stmt->error
    ]);
}

//////////////////////////////////////////////////////
//////////////////// DELETE ///////////////////////////
//////////////////////////////////////////////////////
else if ($action == 'delete') {

    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM doctors WHERE id=?");
    $stmt->bind_param("i", $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Deleted" : $stmt->error
    ]);
}

//////////////////////////////////////////////////////
//////////////////// FETCH ////////////////////////////
//////////////////////////////////////////////////////
else if ($action == 'fetch') {

    $query = "
        SELECT d.*, 
               u.full_name, u.phone, u.email,
               c.name AS category_name
        FROM doctors d
        LEFT JOIN users u ON d.user_id = u.id
        LEFT JOIN doctors_specialized_categories c ON d.specialized_area = c.id
        ORDER BY d.id DESC
    ";

    $result = $conn->query($query);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);
}

//////////////////////////////////////////////////////////////
//////////////////// INVALID ACTION ///////////////////////////
//////////////////////////////////////////////////////////////
else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}