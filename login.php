<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$identifier = $_POST['identifier'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($identifier) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'ID and password are required']);
    exit;
}

try {
    $user_found = false;

    // --- Scenario 1: Check for an approved Owner logging in with their Stall ID ---
    if (strpos($identifier, 'S') === 0) {
        $stmt_owner = $conn->prepare(
            "SELECT sd.stallname, o.password FROM stalldetails sd 
             JOIN Osignup o ON sd.phonenumber = o.phonenumber 
             WHERE sd.stall_id = ? AND sd.approval = 1"
        );
        $stmt_owner->bind_param("s", $identifier);
        $stmt_owner->execute();
        $result_owner = $stmt_owner->get_result();

        if ($result_owner->num_rows === 1) {
            $owner_details = $result_owner->fetch_assoc();
            if (password_verify($password, $owner_details['password'])) {
                $user_found = true;
                $response = [
                    'status' => 'success',
                    'message' => 'Owner login successful!',
                    'role' => 'owner_approved',
                    'data' => [ 'stall_id' => $identifier, 'stall_name' => $owner_details['stallname'] ]
                ];
            }
        }
        $stmt_owner->close();
    }

    // --- Scenario 2: If not a Stall ID, check if it's a User/Student ---
    if (!$user_found) {
        $stmt_user = $conn->prepare("SELECT * FROM usignup WHERE student_id = ? AND is_admin = 0");
        $stmt_user->bind_param("s", $identifier);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();

        if ($result_user->num_rows === 1) {
            $user = $result_user->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $user_found = true;
                $response = [
                    'status' => 'success',
                    'message' => 'User login successful!',
                    'role' => 'student',
                    'data' => [
                        'id' => $user['id'],
                        'fullname' => $user['fullname'],
                        'student_id' => $user['student_id'],
                        'email' => $user['email']
                    ]
                ];
            }
        }
        $stmt_user->close();
    }
    
    // --- Scenario 3: If not found yet, check if it's an Owner by Phone Number ---
    if (!$user_found) {
        $stmt_owner_phone = $conn->prepare(
            "SELECT o.*, sd.approval, sd.stall_id, sd.rejection_reason, sd.stallname 
             FROM Osignup o
             LEFT JOIN stalldetails sd ON o.phonenumber = sd.phonenumber
             WHERE o.phonenumber = ?"
        );
        $stmt_owner_phone->bind_param("s", $identifier);
        $stmt_owner_phone->execute();
        $result_owner_phone = $stmt_owner_phone->get_result();

        if ($result_owner_phone->num_rows === 1) {
            $owner = $result_owner_phone->fetch_assoc();
            if (password_verify($password, $owner['password'])) {
                $user_found = true;
                $response = [
                    'status' => 'success',
                    'message' => 'Owner status check successful!',
                    'role' => 'owner_status_check',
                    'data' => [
                        'stall_status' => $owner['approval'] ?? 0,
                        'stall_id' => $owner['stall_id'] ?? null,
                        'rejection_reason' => $owner['rejection_reason'] ?? null
                    ]
                ];
            }
        }
        $stmt_owner_phone->close();
    }

    if (!$user_found) {
        $response = ['status' => 'error', 'message' => 'Invalid credentials or user not found'];
    }

} catch (mysqli_sql_exception $e) {
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>
