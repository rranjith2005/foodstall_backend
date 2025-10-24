<?php
header('Content-Type: application/json');
include 'config.php';

try {
    $stall_id = $_POST['stall_id'] ?? '';
    $display_order_id = $_POST['display_order_id'] ?? '';
    $new_status = $_POST['new_status'] ?? ''; // Expects "Delivered" or "Rejected"

    if (empty($stall_id) || empty($display_order_id) || empty($new_status)) {
        throw new Exception("Required parameters are missing.");
    }

    $conn->autocommit(FALSE); // Start transaction

    // Get order details but only for pending orders to prevent double processing
    $order_stmt = $conn->prepare(
        "SELECT order_id, student_id, total_amount FROM orders 
         WHERE display_order_id = ? AND stall_id = ? AND order_status = 'Pending'"
    );
    $order_stmt->bind_param("ss", $display_order_id, $stall_id);
    $order_stmt->execute();
    $order_data = $order_stmt->get_result()->fetch_assoc();
    $order_stmt->close();

    if (!$order_data) {
        throw new Exception("Pending order not found or does not belong to this stall.");
    }
    
    // Update the order status and set the refund_timestamp if rejected
    if ($new_status === 'Rejected') {
        $update_stmt = $conn->prepare("UPDATE orders SET order_status = ?, refund_timestamp = NOW() WHERE display_order_id = ?");
        $update_stmt->bind_param("ss", $new_status, $display_order_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE display_order_id = ?");
        $update_stmt->bind_param("ss", $new_status, $display_order_id);
    }
    $update_stmt->execute();
    $update_stmt->close();

    // === UNIFIED WALLET REFUND LOGIC ===
    if ($new_status === 'Rejected') {
        $student_id_to_refund = $order_data['student_id'];
        $amount_to_refund = $order_data['total_amount'];
        $description = "Refunded from " . $display_order_id;

        // 1. Credit the amount back to the user's wallet in the 'usignup' table
        $refund_stmt = $conn->prepare("UPDATE usignup SET wallet_balance = wallet_balance + ? WHERE student_id = ?");
        $refund_stmt->bind_param("ds", $amount_to_refund, $student_id_to_refund);
        $refund_stmt->execute();
        
        if ($refund_stmt->affected_rows == 0) {
            throw new Exception("Wallet not found for the user to refund.");
        }
        $refund_stmt->close();

        // 2. Log the refund in the 'wallet_transactions' table
        $log_stmt = $conn->prepare(
            "INSERT INTO wallet_transactions (student_id, transaction_type, amount, description) VALUES (?, 'Refund', ?, ?)"
        );
        $log_stmt->bind_param("sds", $student_id_to_refund, $amount_to_refund, $description);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    if ($conn->commit()) {
        echo json_encode(["status" => "success", "message" => "Order status updated successfully."]);
    } else {
        throw new Exception("Transaction failed to commit.");
    }

} catch (Exception $e) {
    if ($conn) { $conn->rollback(); }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

if ($conn) { $conn->close(); }
?>