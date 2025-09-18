<?php
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $stmt = $conn->prepare("SELECT * FROM stalldetails WHERE approval = 0 ORDER BY id DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stalls = [];
    while ($row = $result->fetch_assoc()) {
        $stalls[] = $row;
    }
    
    $response = [
        "status" => "success",
        "stalls" => $stalls
    ];

    // --- V V V THIS IS THE FIX V V V ---
    // If the stalls array is empty after the loop, add a message.
    if (empty($stalls)) {
        $response['message'] = "No new stall requests available.";
    }
    // --- ^ ^ ^ END OF FIX ^ ^ ^ ---

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