<?php
date_default_timezone_set('Asia/Kolkata');

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $student_id = $_POST['student_id'] ?? '';
    $stall_id = $_POST['stall_id'] ?? '';
    $order_items_raw = $_POST['order_items'] ?? '';

    $order_date = date('Y-m-d');
    $order_time = date('H:i:s');


    if (empty($student_id) || empty($stall_id) || empty($order_items_raw)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    // Validate stall_id exists in stallsignup
    $check_stall = $conn->prepare("SELECT stall_id FROM stalldetails WHERE stall_id = ?");
    $check_stall->bind_param("s", $stall_id);
    $check_stall->execute();
    $stall_result = $check_stall->get_result();
    if ($stall_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid stall_id"]);
        exit;
    }

    // Validate student_id exists in Usignup
    $check_student = $conn->prepare("SELECT student_id FROM usignup WHERE student_id = ?");
    $check_student->bind_param("s", $student_id);
    $check_student->execute();
    $student_result = $check_student->get_result();
    if ($student_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid student_id"]);
        exit;
    }

    // Decode order_items
    $order_items = json_decode($order_items_raw, true);
    if (!is_array($order_items)) {
        echo json_encode(["status" => "error", "message" => "Invalid order_items format"]);
        exit;
    }

    // Calculate total amount
    $total_amount = 0;
    foreach ($order_items as $item) {
        $item_price = $item['price'] ?? 0;
        $item_quantity = $item['quantity'] ?? 0;
        $total_amount += ($item_price * $item_quantity);
    }

    // Generate order_id prefix (first 3 characters of stall_id)
    $prefix = substr($stall_id, 0, 3);

    // Count existing orders with this prefix
    $check = $conn->prepare("SELECT COUNT(*) as order_count FROM Orders WHERE order_id LIKE CONCAT(?, '%')");
    $check->bind_param("s", $prefix);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $order_number = $row['order_count'] + 1;

    $order_id = $prefix . "-" . $order_number;

    // Insert order
    $stmt = $conn->prepare("
        INSERT INTO Orders (order_id, student_id, stall_id, order_items, total_amount, order_date, order_time)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $order_items_json = json_encode($order_items);
    $stmt->bind_param(
        "sssssss",
        $order_id,
        $student_id,
        $stall_id,
        $order_items_json,
        $total_amount,
        $order_date,
        $order_time
    );
    $stmt->execute();

    echo json_encode([
        "status" => "success",
        "message" => "Order placed successfully",
        "student_id" => $student_id,
        "stall_id" => $stall_id,
        "order_id" => $order_id,
        "total_amount" => $total_amount,
        "order_date" => $order_date,
        "order_time" => $order_time
    ]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Order placement failed: " . $e->getMessage()]);
}
?>
