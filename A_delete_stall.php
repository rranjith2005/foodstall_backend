<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';

if (empty($stall_id)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Stall ID is required.']));
}

try {
    // WARNING: This is a permanent delete. It will remove the stall row from the database.
    // This action cannot be undone and may affect historical order records if they are linked by foreign keys.
    $stmt = $conn->prepare("DELETE FROM stalldetails WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Stall permanently deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Stall not found.']);
        }
    } else {
        throw new Exception("Database delete operation failed.");
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}

$conn->close();
?>