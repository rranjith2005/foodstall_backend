<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $email = $_POST['email'] ?? '';

    // Check if stall exists and already has a stall_id
    $check_existing = $conn->prepare("SELECT stall_id, approval FROM StallDetails WHERE email = ?");
    $check_existing->bind_param("s", $email);
    $check_existing->execute();
    $result_existing = $check_existing->get_result();

    if ($result_existing->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Stall not found"]);
        exit;
    }

    $row_existing = $result_existing->fetch_assoc();

    if (!empty($row_existing['stall_id'])) {
        echo json_encode([
            "status" => "error",
            "message" => "Stall ID already exists for this stall",
            "stall_id" => $row_existing['stall_id']
        ]);
        exit;
    }

    // Generate unique stall_id (S + 5 random digits)
    function generateStallID($length = 5) {
        $numbers = '0123456789';
        $randomNumber = '';
        for ($i = 0; $i < $length; $i++) {
            $randomNumber .= $numbers[rand(0, strlen($numbers) - 1)];
        }
        return 'S' . $randomNumber;
    }

    // Generate stall_id and ensure uniqueness
    do {
        $stall_id = generateStallID();
        $check = $conn->prepare("SELECT * FROM StallDetails WHERE stall_id = ?");
        $check->bind_param("s", $stall_id);
        $check->execute();
        $result = $check->get_result();
    } while ($result->num_rows > 0);

    // Update approval status and set stall_id
    $stmt = $conn->prepare("UPDATE StallDetails SET approval = 1, stall_id = ? WHERE email = ?");
    $stmt->bind_param("ss", $stall_id, $email);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Stall approved successfully",
            "stall_id" => $stall_id
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Stall approval failed or already approved"]);
    }

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Approval failed: " . $e->getMessage()]);
}
?>
