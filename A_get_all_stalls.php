<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // CORRECTED: Changed 'fssai' to 'fssainumber'
    $sql = "SELECT stall_id, stallname, ownername, fssainumber, phonenumber, latitude, longitude, profile_photo FROM stalldetails WHERE approval = 1 ORDER BY stallname ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $stalls = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($stalls);

} catch (Exception $e) { 
    http_response_code(500); 
    echo json_encode(["error" => "An internal server error occurred.", "message" => $e->getMessage()]); 
}

$conn->close();
?>