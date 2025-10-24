<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$student_id = $_POST['student_id'] ?? '';

if (empty($student_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Student ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT fullname, email, phonenumber,profile_photo, student_id FROM usignup WHERE student_id = ? AND is_admin = 0");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $response = [
            'status' => 'success',
            'data' => $user_data
        ];
    } else {
        $response = ['status' => 'error', 'message' => 'User not found'];
    }
    
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>