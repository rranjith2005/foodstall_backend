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
    $seven_days_ago = date("Y-m-d", strtotime("-6 days"));
    $today = date("Y-m-d");

    $stmt = $conn->prepare("
        SELECT order_date, SUM(total_amount) as revenue
        FROM Orders
        WHERE stall_id = ? AND status = 1 AND order_date BETWEEN ? AND ?
        GROUP BY order_date
        ORDER BY order_date ASC
    ");
    $stmt->bind_param("sss", $stall_id, $seven_days_ago, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    $revenue_data = [];

    // Fill default values for all 7 days
    for ($i = 0; $i < 7; $i++) {
        $date = date("Y-m-d", strtotime("-$i days"));
        $revenue_data[$date] = 0;
    }

    while ($row = $result->fetch_assoc()) {
        $revenue_data[$row['order_date']] = (float)$row['revenue'];
    }

    // Return in chronological order
    ksort($revenue_data);

    $response = [];
    foreach ($revenue_data as $date => $revenue) {
        $response[] = [
            "date" => $date,
            "revenue" => $revenue
        ];
    }

    echo json_encode([
        "status" => "success",
        "data" => $response
    ]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Query failed: " . $e->getMessage()
    ]);
}
