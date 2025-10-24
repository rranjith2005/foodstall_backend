<?php
header('Content-Type: application/json');
require 'vendor/autoload.php'; // Make sure this path is correct
include 'config.php';

// Use your actual Razorpay keys
$keyId = 'rzp_test_APuQCp0MiHoD9M';
$keySecret = '06kTw2BRDXPQ3FUuhBZTrPXZ';

use Razorpay\Api\Api;
$api = new Api($keyId, $keySecret);

$rent_amount = $_POST['rent_amount'] ?? 0;
$invoice_id = $_POST['invoice_id'] ?? 0;
$stall_id = $_POST['stall_id'] ?? '';

if ($rent_amount <= 0 || $invoice_id == 0 || empty($stall_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Rent amount and invoice ID are required.']);
    exit;
}

// Amount must be in the smallest currency unit (e.g., paise for INR)
$amountInPaise = $rent_amount * 100;

$orderData = [
    'receipt'         => 'rent_invoice_' . $invoice_id,
    'amount'          => $amountInPaise,
    'currency'        => 'INR',
    'payment_capture' => 1, // Automatically capture the payment
    'notes'           => [
        'invoice_id' => $invoice_id,
        'stall_id'   => $stall_id,
        'type'       => 'rent_payment'
    ]
];

try {
    $razorpayOrder = $api->order->create($orderData);
    $razorpayOrderId = $razorpayOrder['id'];
    
    echo json_encode([
        'status' => 'success',
        'order_id' => $razorpayOrderId,
        'amount' => $amountInPaise,
        'key_id' => $keyId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>