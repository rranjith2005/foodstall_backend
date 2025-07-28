<?php
header('Content-Type: application/json');
include 'config.php';

try {
    // Get order_id and student_id from POST
    $order_id = $_POST['order_id'] ?? '';
    $student_id = $_POST['student_id'] ?? '';

    if (empty($order_id) || empty($student_id)) {
        echo json_encode([
            "status" => "error",
            "message" => "order_id and student_id are required"
        ]);
        exit;
    }

    // Check if order exists and belongs to the student
    $stmt = $conn->prepare("SELECT status FROM Orders WHERE order_id = ? AND student_id = ?");
    $stmt->bind_param("ss", $order_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Order not found for this student"
        ]);
        exit;
    }

    $row = $result->fetch_assoc();
    $current_status = $row['status'];

    // Check current status robustly
    if ($current_status == 1 || $current_status == 0 || $current_status == -1) {
        // Already processed or cancelled
        echo json_encode([
            "status" => "error",
            "message" => "Order status already updated; cannot cancel now"
        ]);
    } else {
        // Treat any other status as pending and cancel
        $update = $conn->prepare("UPDATE Orders SET status = -1 WHERE order_id = ? AND student_id = ?");
        $update->bind_param("ss", $order_id, $student_id);

        if ($update->execute()) {
            echo json_encode([
                "status" => -1,
                "message" => "Your order has been successfully cancelled"
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Failed to cancel order"
            ]);
        }

        $update->close();
    }

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Cancellation failed: " . $e->getMessage()
    ]);
}
?>
