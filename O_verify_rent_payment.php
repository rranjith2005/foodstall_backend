<?php
header('Content-Type: application/json');
require 'vendor/autoload.php'; // Make sure this path is correct
include 'config.php';

$keyId = ' rzp_test_APuQCp0MiHoD9M';
$keySecret = '06kTw2BRDXPQ3FUuhBZTrPXZ';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

$api = new Api($keyId, $keySecret);

$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';
$invoice_id = $_POST['invoice_id'] ?? 0;

if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature) || empty($invoice_id)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Payment details are missing.']));
}

$success = false;

try {
    $attributes = [
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_signature' => $razorpay_signature
    ];
    // This function will throw an exception if the signature is invalid.
    $api->utility->verifyPaymentSignature($attributes);
    $success = true;

} catch(SignatureVerificationError $e) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Razorpay Error: Invalid payment signature.']));
}

if ($success === true) {
    $conn->begin_transaction();
    try {
        // Update the rent_invoices table to mark as paid
        $stmt = $conn->prepare(
            "UPDATE rent_invoices SET status = 'paid', paid_at = NOW() 
             WHERE invoice_id = ? AND status = 'unpaid'"
        );
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Rent payment successful and recorded.']);
        } else {
            // This can happen if the invoice was already paid in another transaction
            $conn->rollback();
            throw new Exception("Invoice already paid or not found.");
        }
        $stmt->close();

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
    }
}

$conn->close();
?>