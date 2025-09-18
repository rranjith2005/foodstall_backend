<?php
header('Content-Type: application/json');
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'Exception.php';
require 'PHPMailer.php';
require 'SMTP.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$email = $_POST['email'] ?? '';

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Email address is required.']);
    exit;
}

try {
    // Check if the email exists in either the user or owner table
    $stmt_user = $conn->prepare("SELECT id FROM usignup WHERE email = ?");
    $stmt_user->bind_param("s", $email);
    $stmt_user->execute();
    $user_exists = $stmt_user->get_result()->num_rows > 0;

    $stmt_owner = $conn->prepare("SELECT id FROM stalldetails WHERE email = ?");
    $stmt_owner->bind_param("s", $email);
    $stmt_owner->execute();
    $owner_exists = $stmt_owner->get_result()->num_rows > 0;

    if (!$user_exists && !$owner_exists) {
        throw new Exception("No account found with that email address.");
    }

    $otp = rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime('+10 minutes'));

    $stmt_otp = $conn->prepare("REPLACE INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
    $stmt_otp->bind_param("sss", $email, $otp, $expiry);
    $stmt_otp->execute();
    
    $mail = new PHPMailer(true);
    
    //Server settings for Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ranjithsuriya12345@gmail.com'; // YOUR GMAIL ADDRESS
    $mail->Password   = 'lloa qpdd owvn qfsp';    // YOUR 16-CHARACTER APP PASSWORD
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('ranjithsuriya12345@gmail.com', 'Stall Spot Support');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your Stall Spot Password Reset Code';
    $mail->Body    = 'Your One-Time Password (OTP) is: <b>' . $otp . '</b>. It is valid for 10 minutes.';
    $mail->send();
    
    echo json_encode([ "status" => "success", "message" => "An OTP has been sent to ranjithsuriya12345@gmail.com" ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Could not send OTP. Error: {$mail->ErrorInfo}"]);
}
?>