<?php
header('Content-Type: application/json');
include 'config.php';

try {
    $phonenumber = $_POST['phonenumber'] ?? '';

    if (empty($phonenumber)) {
        throw new Exception("Owner phone number is required.");
    }

    // Update the stalldetails table by finding the owner via their phone number
    $stmt = $conn->prepare("UPDATE stalldetails SET agreement_accepted = NOW() WHERE phonenumber = ?");
    $stmt->bind_param("s", $phonenumber);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Agreement accepted successfully.']);
        } else {
            throw new Exception("Could not find matching owner record to update.");
        }
    } else {
        throw new Exception("Failed to execute database update.");
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>