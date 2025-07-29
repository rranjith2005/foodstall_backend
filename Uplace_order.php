<?php
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $student_id = $_POST['student_id'] ?? '';
    $stall_id = $_POST['stall_id'] ?? '';
    $order_items = $_POST['order_items'] ?? ''; // should be JSON string

    if (empty($student_id) || empty($stall_id) || empty($order_items)) {
        echo json_encode(["status" => "error", "message" => "student_id, stall_id and order_items are required"]);
        exit;
    }

    $order_items_array = json_decode($order_items, true);
    if (!is_array($order_items_array)) {
        echo json_encode(["status" => "error", "message" => "Invalid order_items format"]);
        exit;
    }

    $total_amount = 0;
    foreach ($order_items_array as &$item) {
        if (!isset($item['quantity'], $item['price'])) {
            echo json_encode(["status" => "error", "message" => "Each item must include quantity and price"]);
            exit;
        }
        $qty = (int)$item['quantity'];
        $price = (float)$item['price'];
        $total_amount += ($qty * $price);

        $item['preparcel'] = isset($item['preparcel']) ? (int)$item['preparcel'] : 0;
        $item['preparcel_time'] = $item['preparcel'] === 1
            ? date("H:i:s", strtotime($item['preparcel_time'] ?? '')) : null;

        if ($item['preparcel'] === 1 && empty($item['preparcel_time'])) {
            echo json_encode(["status" => "error", "message" => "Preparcel time required for preparcel items"]);
            exit;
        }
    }
    unset($item);

    // Step 1: Check student's wallet balance
    $balance_stmt = $conn->prepare("SELECT balance FROM wallet WHERE student_id = ?");
    $balance_stmt->bind_param("s", $student_id);
    $balance_stmt->execute();
    $balance_result = $balance_stmt->get_result();

    if ($balance_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Wallet not found for student"]);
        exit;
    }

    $balance_row = $balance_result->fetch_assoc();
    $current_balance = (float)$balance_row['balance'];

    // Step 2: Verify balance
    if ($current_balance < $total_amount) {
        echo json_encode(["status" => "error", "message" => "Insufficient wallet balance"]);
        exit;
    }

    // Step 3: Deduct amount from wallet
    $new_balance = $current_balance - $total_amount;
    $update_wallet = $conn->prepare("UPDATE wallet SET balance = ? WHERE student_id = ?");
    $update_wallet->bind_param("ds", $new_balance, $student_id);
    $update_wallet->execute();

    // Step 4: Generate unique order ID
    $prefix = substr($stall_id, 0, 3);
    $order_number = 1;
    do {
        $order_id = $prefix . '-' . $order_number;
        $check = $conn->prepare("SELECT COUNT(*) AS count FROM Orders WHERE order_id = ?");
        $check->bind_param("s", $order_id);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $exists = $res['count'] > 0;
        $order_number++;
    } while ($exists);

    // Step 5: Insert order
    $order_items_json = json_encode($order_items_array, JSON_UNESCAPED_UNICODE);
    $order_date = date('Y-m-d');
    $order_time = date('H:i:s');

    $insert = $conn->prepare("
        INSERT INTO Orders (order_id, student_id, stall_id, order_items, total_amount, order_date, order_time, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
    ");
    $insert->bind_param(
        "ssssdss",
        $order_id,
        $student_id,
        $stall_id,
        $order_items_json,
        $total_amount,
        $order_date,
        $order_time
    );
    $insert->execute();

    echo json_encode([
        "status" => "success",
        "message" => "Order placed successfully",
        "order_id" => $order_id,
        "total_amount" => $total_amount,
        "new_wallet_balance" => $new_balance,
        "order_items" => $order_items_array
    ]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Order failed: " . $e->getMessage()]);
}
?>
