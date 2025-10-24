<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
// Admin is identified by a specific student_id, assumed to be 'admin'
$admin_id = $_POST['admin_id'] ?? 'admin'; 

try {
    $stmt = $conn->prepare("SELECT fullname, email, phonenumber,profile_photo FROM usignup WHERE student_id = ? AND is_admin = 1");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin_data = $result->fetch_assoc();
        $response = [
            'status' => 'success',
            'data' => $admin_data
        ];
    } else {
        $response = ['status' => 'error', 'message' => 'Admin user not found'];
    }
    
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>