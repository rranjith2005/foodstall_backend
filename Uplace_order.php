<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get all required data from the Android app
$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$total_amount = $_POST['total_amount'] ?? 0;
$payment_id = $_POST['payment_id'] ?? '';
$pickup_time = $_POST['pickup_time'] ?? null;
$order_items_json = $_POST['order_items'] ?? '[]';
$order_items = json_decode($order_items_json, true);

if (empty($stall_id) || empty($student_id) || $total_amount <= 0 || empty($payment_id) || !is_array($order_items) || empty($order_items)) {
    echo json_encode(['status' => 'error', 'message' => 'Required order details are missing or invalid.']);
    exit;
}

$conn->begin_transaction();

try {
    // --- START OF UPDATED LOGIC ---
    // 1. Count existing orders for this specific stall to get the next order number
    $stmt_count = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE stall_id = ?");
    $stmt_count->bind_param("s", $stall_id);
    $stmt_count->execute();
    $order_count = $stmt_count->get_result()->fetch_assoc()['order_count'];
    $stmt_count->close();
    
    $next_order_number = $order_count + 1;
    
    // 2. Create the custom, human-readable display ID
    $stall_prefix = substr($stall_id, 0, 3); // Takes the first 3 characters, e.g., "S44"
    $display_order_id = $stall_prefix . '-' . $next_order_number;
    // --- END OF UPDATED LOGIC ---

    // 3. Insert the main order details into the 'orders' table
    $payment_method = 'Razorpay';
    $stmt_order = $conn->prepare("INSERT INTO orders (stall_id, student_id, total_amount, payment_method, payment_id, pickup_time, display_order_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_order->bind_param("ssdssss", $stall_id, $student_id, $total_amount, $payment_method, $payment_id, $pickup_time, $display_order_id);
    $stmt_order->execute();
    
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // 4. Loop through and insert items (unchanged)
    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, item_name, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($order_items as $item) {
        if (isset($item['name'], $item['quantity'], $item['price'])) {
            $stmt_items->bind_param("isid", $order_id, $item['name'], $item['quantity'], $item['price']);
            $stmt_items->execute();
        }
    }
    $stmt_items->close();

    $conn->commit();
    // 5. Send the new DISPLAY order ID back to the app
    echo json_encode(['status' => 'success', 'message' => 'Order placed successfully!', 'display_order_id' => $display_order_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}

$conn->close();
?>