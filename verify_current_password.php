<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php'; // Include your database connection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$role = $_POST['role'] ?? '';
$identifier = $_POST['identifier'] ?? ''; // student_id, stall_id/phone, admin_id
$current_password = $_POST['current_password'] ?? '';

if (empty($role) || empty($identifier) || empty($current_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Role, identifier, and current password are required.']);
    exit;
}

try {
    $hashed_password = null;
    $sql = '';
    $bind_type = 's';
    $bind_value = $identifier;

    // Determine table and column based on role
    if ($role === 'USER' || $role === 'ADMIN') { // Assuming USER maps to student, ADMIN to admin in usignup
        $sql = "SELECT password FROM usignup WHERE student_id = ?";
        // You might need different logic if admin identifier is not student_id
        if($role === 'ADMIN' && $identifier === 'admin'){ // Handle hardcoded admin case if necessary
           // Potentially adjust SQL or logic if admin isn't in usignup table
        }
    } elseif ($role === 'OWNER') {
        // Owner identifier could be stall_id or phonenumber
        if (strpos($identifier, 'S') === 0) { // Assume Stall ID
             $sql = "SELECT o.password FROM Osignup o JOIN stalldetails sd ON o.phonenumber = sd.phonenumber WHERE sd.stall_id = ?";
        } else { // Assume Phone Number
            $sql = "SELECT password FROM Osignup WHERE phonenumber = ?";
        }
    } else {
        throw new Exception("Invalid user role specified.");
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param($bind_type, $bind_value);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $hashed_password = $row['password'];

        // Verify the password
        if (password_verify($current_password, $hashed_password)) {
            $response = ['status' => 'success', 'message' => 'Password verified successfully.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Incorrect current password.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'User not found.'];
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
    http_response_code(500);
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()];
    http_response_code(500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>