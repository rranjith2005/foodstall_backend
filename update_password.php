<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php'; // Include your database connection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$role = $_POST['role'] ?? '';
$identifier = $_POST['identifier'] ?? ''; // student_id, stall_id/phone, admin_id
$new_password = $_POST['new_password'] ?? '';

// Basic validation + New password format validation
if (empty($role) || empty($identifier) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Role, identifier, and new password are required.']);
    exit;
}

// Password validation regex (MUST match the one in Java)
$password_regex = "/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[@#$%^&+=!])(?=\S+$).{6,8}$/";
if (!preg_match($password_regex, $new_password)) {
    echo json_encode(["status" => "error", "message" => "New password format is incorrect."]);
    exit;
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $conn->begin_transaction();
    $updated = false;
    $sql = '';
    $bind_type = 'ss'; // Bind hashed password, then identifier
    $bind_value1 = $hashed_password;
    $bind_value2 = $identifier;

    // Determine table and column based on role
    if ($role === 'USER' || $role === 'ADMIN') { // Assuming USER maps to student, ADMIN to admin in usignup
        $sql = "UPDATE usignup SET password = ? WHERE student_id = ?";
         if($role === 'ADMIN' && $identifier === 'admin'){ // Handle hardcoded admin if needed
           // Adjust SQL if admin isn't identified by student_id='admin'
        }
    } elseif ($role === 'OWNER') {
        // Owner identifier could be stall_id or phonenumber - UPDATE based on identifier used
         if (strpos($identifier, 'S') === 0) { // Assume Stall ID - Need to update Osignup via JOIN
             $sql = "UPDATE Osignup o JOIN stalldetails sd ON o.phonenumber = sd.phonenumber SET o.password = ? WHERE sd.stall_id = ?";
         } else { // Assume Phone Number
            $sql = "UPDATE Osignup SET password = ? WHERE phonenumber = ?";
         }
    } else {
        throw new Exception("Invalid user role specified.");
    }

    $stmt = $conn->prepare($sql);
     if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    $stmt->bind_param($bind_type, $bind_value1, $bind_value2);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $updated = true;
    }
    $stmt->close();

    if ($updated) {
        $conn->commit();
        $response = ['status' => 'success', 'message' => 'Password updated successfully!'];
    } else {
        $conn->rollback();
        // Provide a more specific error if possible (e.g., user not found vs. DB error)
        $response = ['status' => 'error', 'message' => 'Failed to update password. User not found or no change made.'];
    }

} catch (mysqli_sql_exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
    http_response_code(500);
} catch (Exception $e) {
     if ($conn->in_transaction) $conn->rollback();
    $response = ['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()];
    http_response_code(500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>