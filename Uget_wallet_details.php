<?php
header('Content-Type: application/json');
include 'config.php';

$student_id = $_POST['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID is required.']);
    exit;
}

try {
    $response = [];

    // 1. Get current wallet balance from usignup table
    $stmt_balance = $conn->prepare("SELECT wallet_balance FROM usignup WHERE student_id = ?");
    $stmt_balance->bind_param("s", $student_id);
    $stmt_balance->execute();
    $response['balance'] = $stmt_balance->get_result()->fetch_assoc()['wallet_balance'] ?? 0.00;
    $stmt_balance->close();

    // 2. Get recent transaction history from the new table
    $stmt_transactions = $conn->prepare(
        "SELECT transaction_type, amount, description, DATE_FORMAT(transaction_date, '%d %b %Y, %h:%i %p') as timestamp 
         FROM wallet_transactions 
         WHERE student_id = ? 
         ORDER BY transaction_date DESC 
         LIMIT 20" // Limit to the last 20 transactions
    );
    $stmt_transactions->bind_param("s", $student_id);
    $stmt_transactions->execute();
    $response['transactions'] = $stmt_transactions->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_transactions->close();

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>