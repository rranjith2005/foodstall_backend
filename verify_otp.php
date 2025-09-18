<?php
header('Content-Type: application/json');
include 'config.php'; // Your database connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$email = $_POST['email'] ?? '';
$otp = $_POST['otp'] ?? '';

if (empty($email) || empty($otp)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and OTP are required.']);
    exit;
}

try {
    // Find the OTP record for the given email
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Invalid request or OTP expired. Please try again.");
    }

    $row = $result->fetch_assoc();
    $current_time = date("Y-m-d H:i:s");

    // Check if the OTP has expired
    if ($row['expires_at'] < $current_time) {
        // Clean up the expired token
        $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt_delete->bind_param("s", $email);
        $stmt_delete->execute();
        throw new Exception("OTP has expired. Please request a new one.");
    }

    // Check if the provided OTP matches the one in the database
    if ($row['otp'] != $otp) {
        throw new Exception("Invalid OTP provided. Please check the code and try again.");
    }
    
    // If we reach here, the OTP is valid and verified
    echo json_encode(['status' => 'success', 'message' => 'OTP verified successfully.']);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>