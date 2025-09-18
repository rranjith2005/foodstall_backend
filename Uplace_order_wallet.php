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
$pickup_time = $_POST['pickup_time'] ?? null;
$order_items_json = $_POST['order_items'] ?? '[]';
$order_items = json_decode($order_items_json, true);

if (empty($stall_id) || empty($student_id) || $total_amount <= 0 || !is_array($order_items) || empty($order_items)) {
    echo json_encode(['status' => 'error', 'message' => 'Required order details are missing or invalid.']);
    exit;
}

// Start a transaction to ensure all queries succeed or none do
$conn->begin_transaction();

try {
    // Step 1: Check if the user's wallet has sufficient balance
    $stmt_bal = $conn->prepare("SELECT wallet_balance FROM usignup WHERE student_id = ? FOR UPDATE");
    $stmt_bal->bind_param("s", $student_id);
    $stmt_bal->execute();
    $current_balance = $stmt_bal->get_result()->fetch_assoc()['wallet_balance'];
    $stmt_bal->close();

    if ($current_balance < $total_amount) {
        throw new Exception("Insufficient wallet balance.");
    }

    // Step 2: Deduct the order amount from the user's wallet
    $new_balance = $current_balance - $total_amount;
    $stmt_update = $conn->prepare("UPDATE usignup SET wallet_balance = ? WHERE student_id = ?");
    $stmt_update->bind_param("ds", $new_balance, $student_id);
    $stmt_update->execute();
    $stmt_update->close();

    // --- START OF UPDATED LOGIC ---
    // Step 3: Count existing orders for this specific stall to get the next number
    $stmt_count = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE stall_id = ?");
    $stmt_count->bind_param("s", $stall_id);
    $stmt_count->execute();
    $order_count = $stmt_count->get_result()->fetch_assoc()['order_count'];
    $stmt_count->close();
    
    $next_order_number = $order_count + 1;
    
    // Step 4: Create the custom, human-readable display ID (e.g., "S44-1")
    $stall_prefix = substr($stall_id, 0, 3);
    $display_order_id = $stall_prefix . '-' . $next_order_number;
    // --- END OF UPDATED LOGIC ---

    // Step 5: Insert the main order details into the 'orders' table
    $payment_method = 'Wallet';
    $payment_id = 'wallet_txn_' . uniqid();
    $stmt_order = $conn->prepare("INSERT INTO orders (stall_id, student_id, total_amount, payment_method, payment_id, pickup_time, display_order_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_order->bind_param("ssdssss", $stall_id, $student_id, $total_amount, $payment_method, $payment_id, $pickup_time, $display_order_id);
    $stmt_order->execute();
    
    $order_id = $conn->insert_id;
    $stmt_order->close();

    // Step 6: Loop through items and insert them into the 'order_items' table
    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, item_name, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($order_items as $item) {
        if (isset($item['name'], $item['quantity'], $item['price'])) {
            $stmt_items->bind_param("isid", $order_id, $item['name'], $item['quantity'], $item['price']);
            $stmt_items->execute();
        }
    }
    $stmt_items->close();

    // If everything was successful, commit the changes to the database
    $conn->commit();
    // Step 7: Send the new DISPLAY order ID back to the app in the success response
    echo json_encode(['status' => 'success', 'message' => 'Order placed successfully!', 'display_order_id' => $display_order_id]);

} catch (Exception $e) {
    // If any step failed, roll back all database changes
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}

$conn->close();
?>