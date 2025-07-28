<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $email = $_POST['email'] ?? '';
    $status = $_POST['status'] ?? null;
    $reason = $_POST['reason'] ?? null;

    if (empty($email) || !in_array($status, ['-1', '0', '1'], true)) {
        echo json_encode(["status" => "error", "message" => "Invalid email or status"]);
        exit;
    }

    // Check if stall exists
    $check = $conn->prepare("SELECT stall_id, approval FROM StallDetails WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Stall not found"]);
        exit;
    }

    $row = $result->fetch_assoc();

    if ($status === '1') {
        // Approve
        if (!empty($row['stall_id']) && $row['stall_id'] !== '-1') {
            echo json_encode([
                "status" => "error",
                "message" => "Stall already approved",
                "stall_id" => $row['stall_id']
            ]);
            exit;
        }

        // Generate unique stall_id
        function generateStallID($length = 5) {
            return 'S' . str_pad(mt_rand(0, 99999), $length, '0', STR_PAD_LEFT);
        }

        do {
            $stall_id = generateStallID();
            $check_id = $conn->prepare("SELECT stall_id FROM StallDetails WHERE stall_id = ?");
            $check_id->bind_param("s", $stall_id);
            $check_id->execute();
            $id_result = $check_id->get_result();
        } while ($id_result->num_rows > 0);

        $update = $conn->prepare("UPDATE StallDetails SET approval = 1, stall_id = ?, rejection_reason = NULL WHERE email = ?");
        $update->bind_param("ss", $stall_id, $email);
        $update->execute();

        echo json_encode([
            "status" => "success",
            "message" => "Stall approved",
            "stall_id" => $stall_id
        ]);

    } elseif ($status === '-1') {
        // Reject
        $stall_id = '-1';
        if (empty($reason)) {
            $reason = "Rejected by admin";
        }

        $update = $conn->prepare("UPDATE StallDetails SET approval = -1, stall_id = ?, rejection_reason = ? WHERE email = ?");
        $update->bind_param("sss", $stall_id, $reason, $email);
        $update->execute();

        echo json_encode([
            "status" => "success",
            "message" => "Stall rejected",
            "stall_id" => $stall_id,
            "reason" => $reason
        ]);

    } elseif ($status === '0') {
        // Pending
        $update = $conn->prepare("UPDATE StallDetails SET approval = 0, stall_id = NULL, rejection_reason = NULL WHERE email = ?");
        $update->bind_param("s", $email);
        $update->execute();

        echo json_encode([
            "status" => "success",
            "message" => "Stall set to pending"
        ]);
    }

    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}
