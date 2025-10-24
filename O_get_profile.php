<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$stall_id = $_POST['stall_id'] ?? '';

if (empty($stall_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Stall ID is required']);
    exit;
}

try {
    // We join with Osignup just in case ownername is more accurate there
    // but stalldetails has all the primary info.
    $stmt = $conn->prepare(
        "SELECT 
            s.stallname, 
            s.ownername, 
            s.phonenumber, 
            s.email, 
            s.profile_photo,
            s.fulladdress, 
            s.fssainumber,
            s.stall_id
        FROM stalldetails s 
        WHERE s.stall_id = ?"
    );
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $owner_data = $result->fetch_assoc();
        $response = [
            'status' => 'success',
            'data' => $owner_data
        ];
    } else {
        $response = ['status' => 'error', 'message' => 'Owner profile not found'];
    }
    
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>