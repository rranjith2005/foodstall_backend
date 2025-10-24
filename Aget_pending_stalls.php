<?php
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // UPDATED: Explicitly select columns and format the date
    $stmt = $conn->prepare("
        SELECT 
            id, stall_id, stallname, ownername, phonenumber, email, fulladdress, fssainumber, 
            DATE_FORMAT(request_date, '%b %d, %Y') as request_date
        FROM stalldetails 
        WHERE approval = 0 
        ORDER BY id DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stalls = $result->fetch_all(MYSQLI_ASSOC);
    
    $response = [
        "status" => "success",
        "stalls" => $stalls
    ];

    if (empty($stalls)) {
        $response['message'] = "No new stall requests available.";
    }

    echo json_encode($response);

    $stmt->close();
    $conn->close(); 

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "A server-side exception occurred: " . $e->getMessage()
    ]);
}
?>