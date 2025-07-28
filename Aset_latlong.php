<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $stall_id = $_POST['stall_id'] ?? '';
    $stall_name = $_POST['stall_name'] ?? ''; // Required for insert
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

    // Check for duplicate latitude and longitude
    $stmt2 = $conn->prepare("SELECT stall_id FROM StallDetails WHERE latitude = ? AND longitude = ? AND stall_id != ?");
    $stmt2->bind_param("sss", $latitude, $longitude, $stall_id);
    $stmt2->execute();
    $duplicate_check = $stmt2->get_result();

    if ($duplicate_check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Another stall with same latitude and longitude already exists"]);
        exit;
    }

    if ($result->num_rows > 0) {
        // Stall exists - update
        $stmt3 = $conn->prepare("UPDATE StallDetails SET latitude = ?, longitude = ? WHERE stall_id = ?");
        $stmt3->bind_param("sss", $latitude, $longitude, $stall_id);
        $stmt3->execute();

        if ($stmt3->affected_rows > 0) {
            echo json_encode(["status" => "success", "message" => "Latitude and longitude updated successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Update failed or no changes made"]);
        }

        $stmt3->close();
    } else {
        // Stall does not exist - insert new stall with location (assuming stall_name is provided)
        if (empty($stall_name)) {
            echo json_encode(["status" => "error", "message" => "Stall does not exist. Provide stall_name to insert as new stall."]);
            exit;
        }

        $stmt4 = $conn->prepare("INSERT INTO StallDetails (stall_id, stall_name, latitude, longitude) VALUES (?, ?, ?, ?)");
        $stmt4->bind_param("ssss", $stall_id, $stall_name, $latitude, $longitude);
        $stmt4->execute();

        echo json_encode(["status" => "success", "message" => "New stall inserted with location successfully"]);
        $stmt4->close();
    }

    $stmt->close();
    $stmt2->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Operation failed: " . $e->getMessage()]);
}
?>
