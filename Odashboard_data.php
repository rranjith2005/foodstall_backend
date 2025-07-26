<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'config.php';

// Get stall_id from GET or POST
$stall_id = $_POST['stall_id'] ?? $_GET['stall_id'] ?? null;
if (!$stall_id) {
    echo json_encode(["status" => "error", "message" => "❌ stall_id is required"]);
    exit;
}

// Get today's date
$today = date('Y-m-d');

// 1. Orders Today
$stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE stall_id = ? AND order_date = ? AND status = 1");
$stmt->bind_param("ss", $stall_id, $today);
$stmt->execute();
$ordersResult = $stmt->get_result()->fetch_assoc();
$total_orders = (int)$ordersResult['total_orders'];

// 2. Revenue Today
$stmt = $conn->prepare("SELECT SUM(total_amount) as total_revenue FROM orders WHERE stall_id = ? AND order_date = ? AND status = 1");
$stmt->bind_param("ss", $stall_id, $today);
$stmt->execute();
$revenueResult = $stmt->get_result()->fetch_assoc();
$total_revenue = (float)$revenueResult['total_revenue'];

// 3. Pending Orders (status ≠ 1)
$stmt = $conn->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE stall_id = ? AND order_date = ? AND status != 1");
$stmt->bind_param("ss", $stall_id, $today);
$stmt->execute();
$pendingResult = $stmt->get_result()->fetch_assoc();
$pending_orders = (int)$pendingResult['pending_orders'];

// 4. Top Selling Item
$stmt = $conn->prepare("SELECT order_items FROM orders WHERE stall_id = ? AND order_date = ? AND status = 1");
$stmt->bind_param("ss", $stall_id, $today);
$stmt->execute();
$result = $stmt->get_result();

$item_counts = [];
while ($row = $result->fetch_assoc()) {
    $items = json_decode($row['order_items'], true);
    if (is_array($items)) {
        foreach ($items as $item) {
            $name = $item['item'];
            $qty = $item['quantity'];
            if (!isset($item_counts[$name])) {
                $item_counts[$name] = 0;
            }
            $item_counts[$name] += $qty;
        }
    }
}

$top_selling = null;
if (!empty($item_counts)) {
    arsort($item_counts);
    $top_selling = array_key_first($item_counts);
}

// Final Response
echo json_encode([
    "status" => "success",
    "stall_id" => $stall_id,
    "orders_today" => $total_orders,
    "revenue_today" => $total_revenue,
    "pending_orders" => $pending_orders,
    "top_selling" => $top_selling
]);
?>
