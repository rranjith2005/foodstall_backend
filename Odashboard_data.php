<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "PHP Error: " . $message,
        "file" => $file,
        "line" => $line
    ]));
});

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';

if (empty($stall_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Stall ID is required.']);
    exit;
}

try {
    $response = [];

    // 1. Get Orders Today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE stall_id = ? AND DATE(order_date) = CURDATE()");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $response['orders_today'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // 2. Get Revenue Today
    $stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE stall_id = ? AND DATE(order_date) = CURDATE()");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $response['revenue_today'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0.00;
    $stmt->close();

    // 3. Get Pending Orders (Using the correct 'order_status' column)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE stall_id = ? AND order_status = 'Pending'");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $response['pending_orders'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt->close();

    // 4. Get Top Selling Item
    $stmt = $conn->prepare(
        "SELECT oi.item_name, SUM(oi.quantity) as total_sold
         FROM order_items oi
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.stall_id = ?
         GROUP BY oi.item_name
         ORDER BY total_sold DESC
         LIMIT 1"
    );
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $top_item = $stmt->get_result()->fetch_assoc();
    $response['top_selling'] = $top_item['item_name'] ?? 'N/A';
    $stmt->close();

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>