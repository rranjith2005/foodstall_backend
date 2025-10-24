<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$student_id = $_POST['student_id'] ?? '';
$order_ids_json = $_POST['order_ids_json'] ?? '';

if (empty($student_id) || empty($order_ids_json)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Student ID and Order IDs are required.']));
}

$order_ids = json_decode($order_ids_json, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($order_ids) || empty($order_ids)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid Order IDs format.']));
}

// Start a transaction
$conn->begin_transaction();

try {
    // Create placeholders for the IN clause (e.g., ?,?,?)
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    
    // Define the data types for binding (s for student_id, followed by 'i' for each integer order_id)
    $types = 's' . str_repeat('i', count($order_ids));
    
    // --- 1. Delete from order_items table first ---
    $sql_items = "DELETE FROM order_items WHERE order_id IN ($placeholders)";
    $stmt_items = $conn->prepare($sql_items);
    // We only need to bind the order_ids here
    $stmt_items->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
    $stmt_items->execute();
    $stmt_items->close();

    // --- 2. Delete from orders table ---
    $sql_orders = "DELETE FROM orders WHERE student_id = ? AND order_id IN ($placeholders)";
    $stmt_orders = $conn->prepare($sql_orders);
    // We need to bind the student_id AND the order_ids
    $stmt_orders->bind_param($types, $student_id, ...$order_ids);
    $stmt_orders->execute();
    $stmt_orders->close();

    // If all queries were successful, commit the transaction
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Orders deleted successfully.']);

} catch (Exception $e) {
    // If any query fails, roll back the transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>