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
        echo json_encode(["status" => "error", "message" => "Invalid email or status provided"]);
        exit;
    }

    $check = $conn->prepare("SELECT stall_id, approval FROM stalldetails WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Stall not found for the given email"]);
        exit;
    }
    $row = $result->fetch_assoc();

    if ($status === '1') {
        // --- APPROVE STALL (Unchanged) ---
        if (!empty($row['stall_id']) && $row['stall_id'] !== NULL && $row['stall_id'] !== '-1') {
            echo json_encode(["status" => "error", "message" => "This stall has already been approved"]);
            exit;
        }

        function generateStallID($length = 5) {
            return 'S' . str_pad(mt_rand(0, 99999), $length, '0', STR_PAD_LEFT);
        }

        do {
            $stall_id = generateStallID();
            $check_id = $conn->prepare("SELECT stall_id FROM stalldetails WHERE stall_id = ?");
            $check_id->bind_param("s", $stall_id);
            $check_id->execute();
            $id_result = $check_id->get_result();
        } while ($id_result->num_rows > 0);

        $update = $conn->prepare("UPDATE stalldetails SET approval = 1, stall_id = ?, rejection_reason = NULL WHERE email = ?");
        $update->bind_param("ss", $stall_id, $email);
        $update->execute();

        echo json_encode(["status" => "success", "message" => "Stall has been approved successfully", "stall_id" => $stall_id]);

    } elseif ($status === '-1') {
        // --- V V V THIS IS THE FIX V V V ---
        // --- REJECT STALL ---
        if (empty($reason)) {
            $reason = "Rejected by admin without a specific reason.";
        }

        // We now set stall_id to NULL instead of '-1' to avoid duplicate key errors.
        $update = $conn->prepare("UPDATE stalldetails SET approval = -1, stall_id = NULL, rejection_reason = ? WHERE email = ?");
        $update->bind_param("ss", $reason, $email);
        $update->execute();

        echo json_encode(["status" => "success", "message" => "Stall has been rejected", "reason" => $reason]);
        // --- ^ ^ ^ END OF FIX ^ ^ ^ ---

    } elseif ($status === '0') {
        // --- SET TO PENDING (Unchanged) ---
        $update = $conn->prepare("UPDATE stalldetails SET approval = 0, stall_id = NULL, rejection_reason = NULL WHERE email = ?");
        $update->bind_param("s", $email);
        $update->execute();
        echo json_encode(["status" => "success", "message" => "Stall status has been reset to pending"]);
    }
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>