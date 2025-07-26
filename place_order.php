<?php
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $student_id = $_POST['student_id'] ?? '';
    $stall_id = $_POST['stall_id'] ?? '';
    $order_items = $_POST['order_items'] ?? '';

    if (empty($student_id) || empty($stall_id) || empty($order_items)) {
        echo json_encode([
            "status" => "error",
            "message" => "student_id, stall_id and order_items are required"
        ]);
        exit;
    }

    $order_items_array = json_decode($order_items, true);
    if (!is_array($order_items_array)) {
        echo json_encode(["status" => "error", "message" => "Invalid order_items format"]);
        exit;
    }

    $total_amount = 0;
    foreach ($order_items_array as $item) {
        $qty = (int)$item['quantity'];
        $price = (float)$item['price'];
        $total_amount += ($qty * $price);
    }

    $order_items_json = json_encode($order_items_array, JSON_UNESCAPED_UNICODE);
    $order_date = date('Y-m-d');
    $order_time = date('H:i:s');

    // Extract prefix from stall_id (first 3 characters)
    $prefix = substr($stall_id, 0, 3);  // e.g., "S07"

    // Keep incrementing number until a unique order_id is found
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

    // Insert order
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
        "order_items" => $order_items_array
    ]);

    $insert->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Order failed: " . $e->getMessage()
    ]);
}
