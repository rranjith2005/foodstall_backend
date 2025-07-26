<?php
date_default_timezone_set("Asia/Kolkata");
header("Content-Type: application/json");
include "config.php";

$stall_id = $_GET['stall_id'] ?? $_POST['stall_id'] ?? '';

if (empty($stall_id)) {
    echo json_encode(["status" => "error", "message" => "stall_id is required"]);
    exit;
}

try {
    $today = date("Y-m-d");

    $stmt = $conn->prepare("
        SELECT HOUR(order_time) AS hour_slot, COUNT(*) AS order_count
        FROM Orders
        WHERE stall_id = ? AND status = 1 AND order_date = ?
        GROUP BY hour_slot
    ");
    $stmt->bind_param("ss", $stall_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    // Build result into associative array
    $hourly_data = [];
    while ($row = $result->fetch_assoc()) {
        $hourly_data[(int)$row['hour_slot']] = (int)$row['order_count'];
    }

    // Define hours from 08 to 20 (8 AM to 8 PM)
    $output = [];
    for ($h = 8; $h <= 20; $h++) {
        $hour_label = str_pad($h, 2, "0", STR_PAD_LEFT) . ":00";
        $order_count = $hourly_data[$h] ?? 0;
        $output[] = [
            "hour" => $hour_label,
            "order_count" => $order_count
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $output
    ]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Query failed: " . $e->getMessage()
    ]);
}
