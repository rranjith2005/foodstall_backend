<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$admin_id = $_POST['admin_id'] ?? 'admin';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phonenumber = $_POST['phonenumber'] ?? '';

if (empty($admin_id) || empty($fullname) || empty($email) || empty($phonenumber)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

// --- SERVER-SIDE VALIDATION ---
if (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
    echo json_encode(['status' => 'error', 'message' => 'Full name must contain only alphabets and spaces']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

if (substr($email, -10) !== '@gmail.com') {
    echo json_encode(['status' => 'error', 'message' => 'Only @gmail.com emails are accepted']);
    exit;
}

if (!preg_match("/^[0-9]{10}$/", $phonenumber)) {
    echo json_encode(['status' => 'error', 'message' => 'Contact must be exactly 10 digits']);
    exit;
}
// --- END OF VALIDATION ---

try {
    // Check if the new email already exists for ANOTHER user
    $stmt_check = $conn->prepare("SELECT id FROM usignup WHERE email = ? AND student_id != ?");
    $stmt_check->bind_param("ss", $email, $admin_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This email is already in use by another account']);
        $stmt_check->close();
        $conn->close();
        exit;
    }
    $stmt_check->close();

    // Update the admin's profile
    $stmt_update = $conn->prepare("UPDATE usignup SET fullname = ?, email = ?, phonenumber = ? WHERE student_id = ? AND is_admin = 1");
    $stmt_update->bind_param("ssss", $fullname, $email, $phonenumber, $admin_id);
    
    if ($stmt_update->execute()) {
        if ($stmt_update->affected_rows > 0) {
            $response = ['status' => 'success', 'message' => 'Admin profile updated successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Admin not found or no changes were made'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'Update failed. Please try again.'];
    }
    
    $stmt_update->close();

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) { // Duplicate entry
        $response = ['status' => 'error', 'message' => 'This email is already in use.'];
    } else {
        $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
    }
}

echo json_encode($response);
$conn->close();
?>