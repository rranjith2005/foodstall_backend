<?php
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');
include 'config.php';

try {
    $student_id = $_GET['student_id'] ?? '';
    $stall_id = $_GET['stall_id'] ?? '';

    if (empty($student_id) && empty($stall_id)) {
        echo json_encode([
            "status" => "error",
            "message" => "student_id or stall_id is required"
        ]);
        exit;
    }

    $orders = [];

    if (!empty($student_id)) {
        // Check if student exists
        $checkStudent = $conn->prepare("SELECT student_id FROM usignup WHERE student_id = ?");
        $checkStudent->bind_param("s", $student_id);
        $checkStudent->execute();
        $studentResult = $checkStudent->get_result();
        if ($studentResult->num_rows == 0) {
            echo json_encode(["status" => "error", "message" => "Invalid student_id"]);
            exit;
        }

        // Get all orders by student
        $stmt = $conn->prepare("
            SELECT * FROM Orders 
            WHERE student_id = ? 
            ORDER BY order_date DESC, order_time DESC
        ");
        $stmt->bind_param("s", $student_id);
    } else {
        // Check if stall exists and is approved
        $checkStall = $conn->prepare("SELECT stall_id FROM stalldetails WHERE stall_id = ? AND approval = 1");
        $checkStall->bind_param("s", $stall_id);
        $checkStall->execute();
        $stallResult = $checkStall->get_result();
        if ($stallResult->num_rows == 0) {
            echo json_encode(["status" => "error", "message" => "Invalid or unapproved stall_id"]);
            exit;
        }

        // Get all orders for the stall
        $stmt = $conn->prepare("
            SELECT * FROM Orders 
            WHERE stall_id = ? 
            ORDER BY order_date DESC, order_time DESC
        ");
        $stmt->bind_param("s", $stall_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['status_text'] = match ((int)$row['status']) {
            1 => 'approved',
            0 => 'rejected',
            -1 => 'user cancelled',
            default => 'pending'
        };
        $row['order_items'] = json_decode($row['order_items'], true);
        unset($row['student_name']); // just in case
        $orders[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "orders" => $orders
    ]);

    $stmt->close();
    $conn->close();
} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Fetch failed: " . $e->getMessage()
    ]);
}
