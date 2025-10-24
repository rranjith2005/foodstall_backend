<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$total_amount = (float)($_POST['total_amount'] ?? 0);
$subtotal = (float)($_POST['subtotal'] ?? 0);
$parcel_fee = (float)($_POST['parcel_fee'] ?? 0);
$payment_id = $_POST['payment_id'] ?? '';
$pickup_time = $_POST['pickup_time'] ?? null;
$order_items_json = $_POST['order_items'] ?? '[]';
$order_items = json_decode($order_items_json, true);

if (empty($stall_id) || empty($student_id) || $total_amount <= 0 || empty($payment_id) || !is_array($order_items) || empty($order_items)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Required order details are missing or invalid.']));
}

$conn->begin_transaction();

try {
    $stmt_max = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(display_order_id, '-', -1) AS UNSIGNED)) as max_order_num FROM orders WHERE stall_id = ?");
    $stmt_max->bind_param("s", $stall_id);
    $stmt_max->execute();
    $result_max = $stmt_max->get_result()->fetch_assoc();
    $max_order_num = $result_max['max_order_num'] ?? 0;
    $stmt_max->close();
    $next_order_number = $max_order_num + 1;
    $stall_prefix = substr($stall_id, 0, 3);
    $display_order_id = $stall_prefix . '-' . $next_order_number;
    
    $parcel_type = 'Dine-in';
    if (is_array($order_items)) {
        $hasParcel = false;
        foreach ($order_items as $item) {
            // --- FIX: Changed 'parcelStatus' to 'parcel_status' ---
            if (isset($item['parcel_status']) && strcasecmp($item['parcel_status'], 'Pre-parcel') == 0) {
                $parcel_type = 'Pre-parcel';
                break;
            }
            if (isset($item['parcel_status']) && strcasecmp($item['parcel_status'], 'Parcel') == 0) {
                $hasParcel = true;
            }
        }
        if ($parcel_type !== 'Pre-parcel' && $hasParcel) {
            $parcel_type = 'Parcel';
        }
    }

    $payment_method = 'Razorpay';
    $stmt_order = $conn->prepare("INSERT INTO orders (stall_id, student_id, total_amount, subtotal, parcel_fee, payment_method, payment_id, parcel_type, pickup_time, display_order_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_order->bind_param("ssdddsssss", $stall_id, $student_id, $total_amount, $subtotal, $parcel_fee, $payment_method, $payment_id, $parcel_type, $pickup_time, $display_order_id);
    $stmt_order->execute();
    $order_id = $conn->insert_id;
    $stmt_order->close();
    
    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, item_name, quantity, price, parcel_status) VALUES (?, ?, ?, ?, ?)");
    foreach ($order_items as $item) {
        if (isset($item['name'], $item['quantity'], $item['price'])) {
            // --- FIX: Changed 'parcelStatus' to 'parcel_status' ---
            $parcel_status = $item['parcel_status'] ?? 'Dine-in';
            $stmt_items->bind_param("isids", $order_id, $item['name'], $item['quantity'], $item['price'], $parcel_status);
            $stmt_items->execute();
        }
    }
    $stmt_items->close();

    $conn->commit();
    
    echo json_encode(['status' => 'success', 'message' => 'Order placed successfully!', 'display_order_id' => $display_order_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}

$conn->close();
?>