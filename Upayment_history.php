<?php
header('Content-Type: application/json');
include 'config.php';

$student_id = $_GET['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode(["status" => "error", "message" => "student_id is required"]);
    exit;
}

// 1. Fetch order payments
$orderQuery = $conn->prepare("
    SELECT order_id AS transaction_id, 'Order Payment' AS type, total_amount AS amount, order_date AS date, order_time AS time
    FROM orders 
    WHERE student_id = ?
");
$orderQuery->bind_param("s", $student_id);
$orderQuery->execute();
$orderResult = $orderQuery->get_result();
$orderData = [];
while ($row = $orderResult->fetch_assoc()) {
    $row['datetime'] = $row['date'] . ' ' . $row['time'];
    unset($row['date'], $row['time']);
    $orderData[] = $row;
}

// 2. Fetch top-up transactions
$topupQuery = $conn->prepare("
    SELECT id AS transaction_id, 'Wallet Top-Up' AS type, amount, created_at AS datetime
    FROM wallet_topups 
    WHERE student_id = ?
");
$topupQuery->bind_param("s", $student_id);
$topupQuery->execute();
$topupResult = $topupQuery->get_result();
$topupData = $topupResult->fetch_all(MYSQLI_ASSOC);

// 3. Merge both transactions
$transactions = array_merge($orderData, $topupData);

// 4. Sort all transactions by datetime (latest first)
usort($transactions, function ($a, $b) {
    return strtotime($b['datetime']) - strtotime($a['datetime']);
});

// 5. Fetch current wallet balance
$balanceQuery = $conn->prepare("SELECT balance FROM wallet WHERE student_id = ?");
$balanceQuery->bind_param("s", $student_id);
$balanceQuery->execute();
$balanceResult = $balanceQuery->get_result();
$balance = 0.00;
if ($row = $balanceResult->fetch_assoc()) {
    $balance = $row['balance'];
}

// 6. Send final response
echo json_encode([
    "status" => "success",
    "balance" => $balance,
    "transactions" => $transactions
]);
