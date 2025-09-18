<?php
header('Content-Type: application/json');
include 'config.php';

$student_id = $_POST['student_id'] ?? '';
if (empty($student_id)) { /* handle error */ exit; }

$stmt = $conn->prepare("SELECT wallet_balance FROM usignup WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc()['wallet_balance'] ?? 0.00;
$stmt->close();

echo json_encode(['status' => 'success', 'balance' => $balance]);
?>