<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 'all', 'approved', 'rejected', 'pending'
$status = $_GET['status'] ?? 'all';

try {
    // [FIX] Updated the SELECT query to include all fields
    // We use aliases (e.g., phonenumber AS phone_number) to make the JSON keys
    // match the @SerializedName in your AdminStall.java model.
    $sql = "
        SELECT 
            stall_id, 
            stallname, 
            ownername, 
            profile_photo, 
            approval, 
            rejection_reason,
            email,
            phonenumber AS phone_number,
            fulladdress AS full_address,
            fssainumber AS fssai_number,
            request_date AS date_requested
        FROM stalldetails
    ";

    // Add a WHERE clause based on the requested status
    switch ($status) {
        case 'approved':
            $sql .= " WHERE approval = 1";
            break;
        case 'rejected':
            $sql .= " WHERE approval = -1";
            break;
        case 'pending':
            $sql .= " WHERE approval = 0";
            break;
        case 'all':
        default:
            // No WHERE clause, get all stalls regardless of approval status
            break;
    }

    $sql .= " ORDER BY request_date DESC";
    
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