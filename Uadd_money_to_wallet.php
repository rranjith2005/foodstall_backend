<?php
header('Content-Type: application/json');
require('razorpay-php/Razorpay.php');
include 'config.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// --- FOR DEBUGGING IN POSTMAN ---
// Set this to true to bypass the security check and test your database logic.
// IMPORTANT: Set this back to false before you launch your app!
$testingMode = true; 
// --- END OF DEBUGGING SWITCH ---

$keyId = 'YOUR_KEY_ID';
$keySecret = 'YOUR_KEY_SECRET';

$student_id = $_POST['student_id'] ?? '';
$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';
$amount = $_POST['amount'] ?? 0;

if (empty($student_id) || empty($razorpay_payment_id) || ($testingMode == false && empty($razorpay_signature)) || $amount <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Required payment details are missing.']);
    exit;
}

$success = false;
if ($testingMode == true) {
    $success = true; // Bypass signature check for Postman testing
} else {
    // ** SECURITY STEP: Verify the payment signature **
    $error = "Payment Failed";
    $api = new Api($keyId, $keySecret);
    try {
        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature
        ];
        $api->utility->verifyPaymentSignature($attributes);
        $success = true;
    } catch(SignatureVerificationError $e) {
        $error = 'Razorpay Error : ' . $e->getMessage();
    }
}


if ($success === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $error]);
    exit;
}

// If signature is verified (or bypassed), proceed with database operations
$conn->begin_transaction();
try {
    // 1. Update user's wallet balance
    $stmt_update = $conn->prepare("UPDATE usignup SET wallet_balance = wallet_balance + ? WHERE student_id = ?");
    $stmt_update->bind_param("ds", $amount, $student_id);
    $stmt_update->execute();
    $stmt_update->close();

    // 2. Log this transaction in the history table
    $transaction_type = "Added Money";
    $description = "Via Razorpay (ID: " . $razorpay_payment_id . ")";
    $stmt_log = $conn->prepare("INSERT INTO wallet_transactions (student_id, transaction_type, amount, description) VALUES (?, ?, ?, ?)");
    $stmt_log->bind_param("ssds", $student_id, $transaction_type, $amount, $description);
    $stmt_log->execute();
    $stmt_log->close();

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Amount added to wallet successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    // Provide a more detailed error message for debugging
    echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $e->getMessage()]);
}
?>