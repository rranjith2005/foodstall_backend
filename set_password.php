<?php
header('Content-Type: application/json');
include 'config.php';

// --- DEBUGGING SETUP ---
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log('--- New set_password.php request ---');
// ------------------------

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$role = $_POST['role'] ?? '';
$identifier = $_POST['identifier'] ?? '';
$new_password = $_POST['new_password'] ?? '';

error_log('Received role: ' . $role);
error_log('Received identifier: ' . $identifier);
error_log('Received new_password: ' . $new_password);

if (empty($role) || empty($identifier) || empty($new_password)) {
    error_log('Validation failed: Missing required fields.');
    echo json_encode(['status' => 'error', 'message' => 'Role, identifier, and new password are required.']);
    exit;
}

$password_regex = "/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[@#$%^&+=!])(?=\S+$).{6,8}$/";

if (!preg_match($password_regex, $new_password)) {
    error_log('Validation failed: Regex did not match.');
    // The error message has been simplified to match the Android logcat.
    echo json_encode(["status" => "error", "message" => "Password format is incorrect"]);
    exit;
}

error_log('Validation successful: Regex matched.');

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $conn->begin_transaction();
    $updated = false;

    if ($role === 'user') {
        $stmt = $conn->prepare("UPDATE usignup SET password = ? WHERE student_id = ?");
        $stmt->bind_param("ss", $hashed_password, $identifier);
        $stmt->execute();
        if ($stmt->affected_rows > 0) $updated = true;
        $stmt->close();

    } elseif ($role === 'owner') {
        $stmt = $conn->prepare("UPDATE Osignup SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $identifier);
        $stmt->execute();
        if ($stmt->affected_rows > 0) $updated = true;
        $stmt->close();

    } else {
        throw new Exception("Invalid role specified: {$role}");
    }

    if ($updated) {
        $conn->commit();
        $response = ['status' => 'success', 'message' => 'Password updated successfully!'];
    } else {
        $conn->rollback();
        $response = ['status' => 'error', 'message' => 'Failed to update. User/Owner not found.'];
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    http_response_code(500);
    error_log('Caught Exception: ' . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
}

echo json_encode($response);

if (isset($conn)) {
    $conn->close();
}
?>