<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
include 'config.php';
require 'vendor/autoload.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

$keyId = 'zp_test_APuQCp0MiHoD9M';
$keySecret = '06kTw2BRDXPQ3FUuhBZTrPXZ';
$api = new Api($keyId, $keySecret);

$student_id = $_POST['student_id'] ?? '';
$amount = $_POST['amount'] ?? 0;
$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';

if (empty($student_id) || empty($razorpay_payment_id) || $amount <= 0) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Missing required payment details.']));
}

try {
    $attributes = [
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ];
    $api->utility->verifyPaymentSignature($attributes);
} catch(SignatureVerificationError $e) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Razorpay signature verification failed.']));
}

$conn->begin_transaction();
try {
    // --- START OF THE FIX ---
    // This query now updates the 'wallet_balance' in your 'usignup' table.
    $stmt_update = $conn->prepare("UPDATE usignup SET wallet_balance = wallet_balance + ? WHERE student_id = ?");
    $stmt_update->bind_param("ds", $amount, $student_id);
    $stmt_update->execute();
    
    // Check if the update was successful
    if ($stmt_update->affected_rows == 0) {
        throw new Exception("Student ID not found in usignup table, could not update balance.");
    }
    $stmt_update->close();
    // --- END OF THE FIX ---

    $transaction_type = 'Added Money';
    $description = 'Via Razorpay: ' . $razorpay_payment_id;
    $stmt_log = $conn->prepare("INSERT INTO wallet_transactions (student_id, transaction_type, amount, description) VALUES (?, ?, ?, ?)");
    $stmt_log->bind_param("ssds", $student_id, $transaction_type, $amount, $description);
    $stmt_log->execute();
    $stmt_log->close();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Wallet updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $e->getMessage()]);
}

$conn->close();
?>