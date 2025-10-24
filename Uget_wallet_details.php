<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$student_id = $_POST['student_id'] ?? '';

if (empty($student_id)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Student ID is required.']));
}

try {
    // Correctly reads 'wallet_balance' from the 'usignup' table.
    $stmt_balance = $conn->prepare("SELECT wallet_balance FROM usignup WHERE student_id = ?");
    $stmt_balance->bind_param("s", $student_id);
    $stmt_balance->execute();
    $balance_result = $stmt_balance->get_result()->fetch_assoc();
    $balance = $balance_result['wallet_balance'] ?? 0.00;
    $stmt_balance->close();

    // Correctly fetches the transaction history.
    $stmt_transactions = $conn->prepare("SELECT * FROM wallet_transactions WHERE student_id = ? ORDER BY transaction_date DESC LIMIT 50");
    $stmt_transactions->bind_param("s", $student_id);
    $stmt_transactions->execute();
    $transactions = $stmt_transactions->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_transactions->close();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'balance' => (float)$balance,
            'transactions' => $transactions
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
$conn->close();
?>