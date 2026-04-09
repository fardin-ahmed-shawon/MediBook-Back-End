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
if ($action == 'add-doctor-category') {

    $name = $_POST['name'] ?? '';

    if (empty($name)) {
        echo json_encode(["success" => false, "message" => "Name required"]);
        exit();
    }

    // Check duplicate
    $stmt = $conn->prepare("SELECT id FROM doctors_specialized_categories WHERE name=?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Category already exists"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO doctors_specialized_categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => "Category added successfully"
    ]);
}

//////////////////////////////////////////////////////
//////////////////// EDIT /////////////////////////////
//////////////////////////////////////////////////////
else if ($action == 'edit-doctor-category') {

    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';

    if (!$id || !$name) {
        echo json_encode(["success" => false, "message" => "All fields required"]);
        exit();
    }

    $stmt = $conn->prepare("UPDATE doctors_specialized_categories SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Updated" : $stmt->error
    ]);
}

//////////////////////////////////////////////////////
//////////////////// DELETE ///////////////////////////
//////////////////////////////////////////////////////
else if ($action == 'delete-doctor-category') {

    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM doctors_specialized_categories WHERE id=?");
    $stmt->bind_param("i", $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Deleted" : $stmt->error
    ]);
}

//////////////////////////////////////////////////////
//////////////////// FETCH ////////////////////////////
//////////////////////////////////////////////////////
else if ($action == 'fetch-doctor-categories') {

    $result = $conn->query("SELECT * FROM doctors_specialized_categories ORDER BY id DESC");

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