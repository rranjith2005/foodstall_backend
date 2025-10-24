<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';
$filter = $_POST['filter'] ?? 'overall';

if (empty($stall_id)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Stall ID is required."]));
}

try {
    $date_condition = "";
    if ($filter == 'year') {
        $date_condition = "AND YEAR(o.order_date) = YEAR(CURDATE())";
    }

    // This query correctly calculates totals from the 'orders' table.
    $summary_sql = "
        SELECT 
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(COUNT(DISTINCT o.order_id), 0) as total_orders
        FROM orders o
        WHERE o.stall_id = ? AND o.order_status = 'Delivered' $date_condition
    ";
    $stmt_summary = $conn->prepare($summary_sql);
    $stmt_summary->bind_param("s", $stall_id);
    $stmt_summary->execute();
    $summary_result = $stmt_summary->get_result()->fetch_assoc();
    $stmt_summary->close();

    // Base query that now ONLY uses order_items and orders tables.
    $items_base_sql = "
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.stall_id = ? AND o.order_status = 'Delivered' $date_condition
        GROUP BY oi.item_name
    ";

    // Query for Top 3 Performers (no longer needs image)
    $top_sql = "
        SELECT 
            oi.item_name, 
            SUM(oi.quantity) as total_quantity, 
            SUM(oi.price * oi.quantity) as total_revenue
        $items_base_sql
        ORDER BY total_revenue DESC
        LIMIT 3
    ";
    $stmt_top = $conn->prepare($top_sql);
    $stmt_top->bind_param("s", $stall_id);
    $stmt_top->execute();
    $top_performers = $stmt_top->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_top->close();

    // Query for 3 Items Needing Attention
    $attention_sql = "
        SELECT 
            oi.item_name, 
            SUM(oi.quantity) as total_quantity, 
            SUM(oi.price * oi.quantity) as total_revenue
        $items_base_sql
        ORDER BY total_revenue ASC
        LIMIT 3
    ";
    $stmt_attention = $conn->prepare($attention_sql);
    $stmt_attention->bind_param("s", $stall_id);
    $stmt_attention->execute();
    $needs_attention = $stmt_attention->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_attention->close();

    $response = [
        'status' => 'success',
        'data' => [
            'total_revenue' => (float)$summary_result['total_revenue'],
            'total_orders' => (int)$summary_result['total_orders'],
            'top_performers' => $top_performers,
            'needs_attention' => $needs_attention
        ]
    ];
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
$conn->close();
?>