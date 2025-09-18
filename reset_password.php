<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$email = $_POST['email'] ?? '';
$otp = $_POST['otp'] ?? '';
$new_password = $_POST['password'] ?? '';

if (empty($email) || empty($otp) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email, OTP, and new password are required.']);
    exit;
}

try {
    // --- Step 1: Re-verify the OTP for security ---
    $stmt_verify = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ?");
    $stmt_verify->bind_param("ss", $email, $otp);
    $stmt_verify->execute();
    $result = $stmt_verify->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Invalid OTP or email. Password reset failed for security reasons.");
    }
    // OTP is valid, we can proceed.

    // --- Step 2: Hash the new password for secure storage ---
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // --- Step 3: Find which table to update (usignup or Osignup) and perform the update ---
    $updated = false;
    
    // Attempt to update the 'usignup' table (for students and admins)
    $stmt_user = $conn->prepare("UPDATE usignup SET password = ? WHERE email = ?");
    $stmt_user->bind_param("ss", $hashed_password, $email);
    if ($stmt_user->execute() && $stmt_user->affected_rows > 0) {
        $updated = true;
    }
    $stmt_user->close();

    // If no user was updated, attempt to update the 'Osignup' table (for owners)
    if (!$updated) {
        $stmt_owner = $conn->prepare("UPDATE Osignup o JOIN stalldetails sd ON o.phonenumber = sd.phonenumber SET o.password = ? WHERE sd.email = ?");
        $stmt_owner->bind_param("ss", $hashed_password, $email);
        if ($stmt_owner->execute() && $stmt_owner->affected_rows > 0) {
            $updated = true;
        }
        $stmt_owner->close();
    }

    if (!$updated) {
        throw new Exception("Could not find an account with that email to update.");
    }

    // --- Step 4: Delete the used OTP from the resets table for security ---
    $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
    $stmt_delete->bind_param("s", $email);
    $stmt_delete->execute();
    $stmt_delete->close();
    
    echo json_encode(['status' => 'success', 'message' => 'Your password has been updated successfully.']);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>