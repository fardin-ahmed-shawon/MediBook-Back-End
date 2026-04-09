<?php
require_once './config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if (!$action) {
    echo json_encode(["success" => false, "message" => "No action"]);
    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// ADD APPOINTMENT //////////////////////////
//////////////////////////////////////////////////////////////
if ($action == 'add-appointment') {

    $doctor_user_id = $_POST['doctor_user_id'] ?? '';
    $hospital_location = $_POST['hospital_location'] ?? '';
    $hospital_name = $_POST['hospital_name'] ?? '';
    $chamber_location = $_POST['chamber_location'] ?? '';
    $visiting_fee = $_POST['visiting_fee'] ?? 0;

    // schedules should come as JSON array
    $schedules_json = $_POST['schedules'] ?? '';

    // JSON format example:
    // [{"day":"Sunday","start_time":"10:00:00","duration":20},{"day":"Tuesday","start_time":"14:00:00","duration":30}]

    if (!$doctor_user_id || empty($schedules_json)) {
        echo json_encode(["success" => false, "message" => "Doctor & schedules required"]);
        exit();
    }

    $schedules = json_decode($schedules_json, true);

    if (!is_array($schedules) || count($schedules) == 0) {
        echo json_encode(["success" => false, "message" => "Invalid schedules"]);
        exit();
    }

    // START TRANSACTION 
    $conn->begin_transaction();

    try {

        // Insert Appointment
        $stmt = $conn->prepare("INSERT INTO appointments 
            (doctor_user_id, hospital_location, hospital_name, chamber_location, visiting_fee) 
            VALUES (?, ?, ?, ?, ?)");

        $stmt->bind_param("isssd", $doctor_user_id, $hospital_location, $hospital_name, $chamber_location, $visiting_fee);
        $stmt->execute();

        $appointment_id = $conn->insert_id;

        //  Insert Multiple Schedules
        $stmt = $conn->prepare("INSERT INTO appointment_schedules 
            (appointment_id, appointment_day, available_start_time, appointment_duration_max) 
            VALUES (?, ?, ?, ?)");

        foreach ($schedules as $sch) {

            $day = $sch['day'] ?? '';
            $start = $sch['start_time'] ?? '';
            $duration = $sch['duration'] ?? '';

            if (!$day || !$start || !$duration) {
                throw new Exception("Invalid schedule data");
            }

            $stmt->bind_param("issi", $appointment_id, $day, $start, $duration);
            $stmt->execute();
        }

        // COMMIT
        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Appointment with schedules created"
        ]);

    } catch (Exception $e) {

        // ROLLBACK
        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// UPDATE APPOINTMENT ///////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'edit-appointment') {

    $appointment_id = $_POST['appointment_id'] ?? '';
    $hospital_location = $_POST['hospital_location'] ?? '';
    $hospital_name = $_POST['hospital_name'] ?? '';
    $chamber_location = $_POST['chamber_location'] ?? '';
    $visiting_fee = $_POST['visiting_fee'] ?? 0;
    $schedules_json = $_POST['schedules'] ?? '';

    if (!$appointment_id) {
        echo json_encode(["success" => false, "message" => "Appointment ID required"]);
        exit();
    }

    $schedules = json_decode($schedules_json, true);

    $conn->begin_transaction();

    try {

        // Update appointment
        $stmt = $conn->prepare("UPDATE appointments 
            SET hospital_location=?, hospital_name=?, chamber_location=?, visiting_fee=? 
            WHERE id=?");

        $stmt->bind_param("sssdi", $hospital_location, $hospital_name, $chamber_location, $visiting_fee, $appointment_id);
        $stmt->execute();

        // Delete old schedules
        $conn->query("DELETE FROM appointment_schedules WHERE appointment_id = $appointment_id");

        // Insert new schedules
        if (is_array($schedules)) {

            $stmt = $conn->prepare("INSERT INTO appointment_schedules 
                (appointment_id, appointment_day, available_start_time, appointment_duration_max) 
                VALUES (?, ?, ?, ?)");

            foreach ($schedules as $sch) {

                $day = $sch['day'];
                $start = $sch['start_time'];
                $duration = $sch['duration'];

                $stmt->bind_param("issi", $appointment_id, $day, $start, $duration);
                $stmt->execute();
            }
        }

        $conn->commit();

        echo json_encode([
            "success" => true,
            "message" => "Appointment updated"
        ]);

    } catch (Exception $e) {

        $conn->rollback();

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// DELETE /////////////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'delete-appointment') {

    $id = $_POST['id'] ?? '';

    if (!$id) {
        echo json_encode(["success" => false, "message" => "ID required"]);
        exit();
    }

    // CASCADE will auto delete schedules
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id=?");
    $stmt->bind_param("i", $id);

    echo json_encode([
        "success" => $stmt->execute(),
        "message" => $stmt->execute() ? "Deleted" : $stmt->error
    ]);

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// FETCH ///////////////////////////////////
//////////////////////////////////////////////////////////////
else if ($action == 'fetch-appointments') {

    $query = "
        SELECT a.*, u.full_name 
        FROM appointments a
        LEFT JOIN users u ON a.doctor_user_id = u.id
        ORDER BY a.id DESC
    ";

    $result = $conn->query($query);

    $data = [];

    while ($row = $result->fetch_assoc()) {

        // Get schedules
        $sch_res = $conn->query("SELECT * FROM appointment_schedules WHERE appointment_id=" . $row['id']);
        $schedules = [];

        while ($s = $sch_res->fetch_assoc()) {
            $schedules[] = $s;
        }

        $row['schedules'] = $schedules;
        $data[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);

    exit();
}

//////////////////////////////////////////////////////////////
//////////////////// INVALID /////////////////////////////////
//////////////////////////////////////////////////////////////
else {
    echo json_encode(["success" => false, "message" => "Invalid action"]);
}