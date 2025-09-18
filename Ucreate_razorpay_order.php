<?php
header('Content-Type: application/json');
// This line is crucial to include the library you just downloaded
require('razorpay-php/Razorpay.php'); 
include 'config.php';

// --- START OF UPDATED SECTION ---
// Replace these placeholder values with your actual Test Keys from the Razorpay Dashboard
$keyId = 'rzp_test_APuQCp0MiHoD9M';
$keySecret = '06kTw2BRDXPQ3FUuhBZTrPXZ';
// --- END OF UPDATED SECTION ---


use Razorpay\Api\Api;
$api = new Api($keyId, $keySecret);

$totalAmount = $_POST['amount'] ?? 0;

if ($totalAmount == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Amount is required.']);
    exit;
}

// Amount must be in the smallest currency unit (e.g., paise for INR)
$amountInPaise = $totalAmount * 100;

$orderData = [
    'receipt'         => 'rcptid_' . uniqid(),
    'amount'          => $amountInPaise,
    'currency'        => 'INR',
    'payment_capture' => 1 // Automatically capture the payment
];

try {
    $razorpayOrder = $api->order->create($orderData);
    $razorpayOrderId = $razorpayOrder['id'];
    // Send the order_id back to the Android app
    echo json_encode(['status' => 'success', 'order_id' => $razorpayOrderId, 'amount' => $amountInPaise, 'key_id' => $keyId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>