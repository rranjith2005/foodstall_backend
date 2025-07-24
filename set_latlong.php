<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $stall_id = $_POST['stall_id'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';

    if (empty($stall_id) || empty($latitude) || empty($longitude)) {
        echo json_encode(["status" => "error", "message" => "stall_id, latitude, and longitude are required"]);
        exit;
    }

    // Check if stall exists
    $stmt = $conn->prepare("SELECT * FROM StallDetails WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Stall not found"]);
        exit;
    }

    // Check if latitude and longitude already exist for another stall
    $stmt = $conn->prepare("SELECT stall_id FROM StallDetails WHERE latitude = ? AND longitude = ? AND stall_id != ?");
    $stmt->bind_param("sss", $latitude, $longitude, $stall_id);
    $stmt->execute();
    $duplicate_check = $stmt->get_result();

    if ($duplicate_check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Another stall with the same latitude and longitude already exists"]);
        exit;
    }

    // Update latitude and longitude
    $stmt = $conn->prepare("UPDATE StallDetails SET latitude = ?, longitude = ? WHERE stall_id = ?");
    $stmt->bind_param("sss", $latitude, $longitude, $stall_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Latitude and longitude updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed or no changes made"]);
    }

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $e->getMessage()]);
}
?>
