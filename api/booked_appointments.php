<?php
require_once './config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if (!$action) {
    echo json_encode(["success" => false, "message" => "No action"]);
    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// BOOK APPOINTMENT /////////////////////////
//////////////////////////////////////////////////////////////
if ($action == 'add-appointment-booking') {

    $booking_user_id = $_POST['booking_user_id'] ?? '';
    $appointment_id = $_POST['appointment_id'] ?? '';
    $appointment_schedule_id = $_POST['appointment_schedule_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';

    if (!$booking_user_id || !$appointment_id || !$appointment_schedule_id || !$appointment_date) {
        echo json_encode([
            "success" => false,
            "message" => "All fields required"
        ]);
        exit();
    }

    // 🔥 Prevent duplicate booking (same slot same date)
    $stmt = $conn->prepare("
        SELECT id FROM booked_appointments 
        WHERE appointment_schedule_id = ? 
        AND appointment_date = ?
    ");
    $stmt->bind_param("is", $appointment_schedule_id, $appointment_date);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "message" => "This time slot is already booked"
        ]);
        exit();
    }

    // Insert booking
    $stmt = $conn->prepare("
        INSERT INTO booked_appointments 
        (booking_user_id, appointment_id, appointment_schedule_id, appointment_date) 
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("iiis", $booking_user_id, $appointment_id, $appointment_schedule_id, $appointment_date);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Appointment booked successfully"
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
//////////////////// FETCH BOOKINGS ///////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'fetch-appointment-bookings') {

    $query = "
        SELECT 
            b.*,
            u.full_name AS patient_name,
            d_user.full_name AS doctor_name,
            a.hospital_name,
            a.hospital_location,
            a.chamber_location,
            a.visiting_fee,
            s.appointment_day,
            s.available_start_time,
            s.appointment_duration_max

        FROM booked_appointments b

        LEFT JOIN users u ON b.booking_user_id = u.id
        LEFT JOIN appointments a ON b.appointment_id = a.id
        LEFT JOIN appointment_schedules s ON b.appointment_schedule_id = s.id
        LEFT JOIN users d_user ON a.doctor_user_id = d_user.id

        ORDER BY b.id DESC
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

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// DELETE BOOKING ///////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'delete') {

    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode([
            "success" => false,
            "message" => "Booking ID required"
        ]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM booked_appointments WHERE id=?");
    $stmt->bind_param("i", $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Booking deleted" : $stmt->error
    ]);

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// EDIT BOOKING /////////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'edit') {

    $id = $_POST['id'] ?? '';
    $appointment_schedule_id = $_POST['appointment_schedule_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';

    if (!$id || !$appointment_schedule_id || !$appointment_date) {
        echo json_encode([
            "success" => false,
            "message" => "All fields required"
        ]);
        exit();
    }

    // Prevent conflict again
    $stmt = $conn->prepare("
        SELECT id FROM booked_appointments 
        WHERE appointment_schedule_id = ? 
        AND appointment_date = ?
        AND id != ?
    ");
    $stmt->bind_param("isi", $appointment_schedule_id, $appointment_date, $id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "message" => "This slot already booked"
        ]);
        exit();
    }

    $stmt = $conn->prepare("
        UPDATE booked_appointments 
        SET appointment_schedule_id=?, appointment_date=? 
        WHERE id=?
    ");
    $stmt->bind_param("isi", $appointment_schedule_id, $appointment_date, $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Booking updated" : $stmt->error
    ]);

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// INVALID /////////////////////////////////
//////////////////////////////////////////////////////////////
else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid action"
    ]);
}