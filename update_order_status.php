<?php
date_default_timezone_set('Asia/Kolkata');
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $order_id = $_POST['order_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($order_id) || ($status !== '1' && $status !== '0')) {
        echo json_encode(["status" => "error", "message" => "Valid order_id and status (1 or 0) are required"]);
        exit;
    }

    // Check if order exists
    $check = $conn->prepare("SELECT * FROM Orders WHERE order_id = ?");
    $check->bind_param("s", $order_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Order not found"]);
        exit;
    }

    // Update order status
    $update = $conn->prepare("UPDATE Orders SET status = ? WHERE order_id = ?");
    $update->bind_param("ss", $status, $order_id);
    $update->execute();

    // Convert status to text for output
    $status_text = ($status === '1') ? "approved" : "rejected";

    echo json_encode([
        "status" => "success",
        "message" => "Order status updated successfully",
        "order_id" => $order_id,
        "updated_status" => $status_text
    ]);

    $update->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $e->getMessage()]);
}
?>
