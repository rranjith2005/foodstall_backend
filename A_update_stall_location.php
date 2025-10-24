<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';

if (empty($stall_id) || empty($latitude) || empty($longitude)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Stall ID and location are required.']));
}

try {
    $stmt = $conn->prepare("UPDATE stalldetails SET latitude = ?, longitude = ? WHERE stall_id = ?");
    $stmt->bind_param("sss", $latitude, $longitude, $stall_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Location updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Stall not found or location is already the same.']);
        }
    } else {
        throw new Exception("Database update failed.");
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}

$conn->close();
?>