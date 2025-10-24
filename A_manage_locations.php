<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$action = $_POST['action'] ?? '';
if (empty($action)) { die(json_encode(["status" => "error", "message" => "Action not specified."])); }

try {
    switch ($action) {
        case 'get_locations':
            handle_get_locations($conn);
            break;
        case 'set_location':
            handle_set_location($conn);
            break;
        case 'delete_location':
            handle_delete_location($conn);
            break;
        default:
            throw new Exception("Invalid action.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

function handle_get_locations($conn) {
    $stmt = $conn->prepare("SELECT stall_id, stallname, latitude, longitude FROM stalldetails WHERE approval = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY stallname ASC");
    $stmt->execute();
    $locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(["status" => "success", "locations" => $locations]);
}

function handle_set_location($conn) {
    $stall_id = $_POST['stall_id'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';

    if (empty($stall_id) || empty($latitude) || empty($longitude)) {
        throw new Exception("Stall ID, latitude, and longitude are required.");
    }

    $stmt = $conn->prepare("UPDATE stalldetails SET latitude = ?, longitude = ? WHERE stall_id = ? AND approval = 1");
    $stmt->bind_param("dds", $latitude, $longitude, $stall_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Location updated successfully for stall " . $stall_id]);
    } else {
        throw new Exception("Stall ID not found or is not an approved stall.");
    }
    $stmt->close();
}

function handle_delete_location($conn) {
    $stall_id = $_POST['stall_id'] ?? '';
    if (empty($stall_id)) {
        throw new Exception("Stall ID is required.");
    }
    
    // Deleting a location means setting latitude and longitude to NULL
    $stmt = $conn->prepare("UPDATE stalldetails SET latitude = NULL, longitude = NULL WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Location for " . $stall_id . " has been deleted."]);
    } else {
        throw new Exception("Stall ID not found.");
    }
    $stmt->close();
}

$conn->close();
?>