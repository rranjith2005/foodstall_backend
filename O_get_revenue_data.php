<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';

if (empty($stall_id)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Stall ID is required."]));
}

try {
    // --- 1. Get Summary Totals (This query is correct) ---
    $summary_sql = "
        SELECT
            COALESCE(SUM(CASE WHEN DATE(order_date) = CURDATE() THEN total_amount ELSE 0 END), 0) as todays_revenue,
            COALESCE(SUM(CASE WHEN YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) THEN total_amount ELSE 0 END), 0) as this_weeks_revenue,
            COALESCE(SUM(CASE WHEN YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_months_revenue
        FROM orders
        WHERE stall_id = ? AND order_status = 'Delivered'
    ";
    
    $stmt_summary = $conn->prepare($summary_sql);
    $stmt_summary->bind_param("s", $stall_id);
    $stmt_summary->execute();
    $summary_result = $stmt_summary->get_result()->fetch_assoc();
    $stmt_summary->close();

    // --- 2. Get Daily Revenue Details for the Table ---
    $details_sql = "
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m-%d') as date,
            COUNT(order_id) as orders,
            SUM(total_amount) as revenue
        FROM orders
        WHERE stall_id = ? AND order_status = 'Delivered'
        GROUP BY date -- <<< THIS IS THE FIX (Changed from GROUP BY DATE(order_date))
        ORDER BY date DESC
    ";

    $stmt_details = $conn->prepare($details_sql);
    $stmt_details->bind_param("s", $stall_id);
    $stmt_details->execute();
    $details_result = $stmt_details->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_details->close();

    // --- 3. Combine All Data into a Single Response ---
    $response = [
        'status' => 'success',
        'data' => [
            'todays_revenue' => (float)$summary_result['todays_revenue'],
            'this_weeks_revenue' => (float)$summary_result['this_weeks_revenue'],
            'this_months_revenue' => (float)$summary_result['this_months_revenue'],
            'daily_details' => $details_result
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) { 
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "An internal server error occurred.", "details" => $e->getMessage()]); 
}

$conn->close();
?>