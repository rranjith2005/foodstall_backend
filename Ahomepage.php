<?php
header("Content-Type: application/json");
include 'config.php';

try {
    // Total stalls
    $total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM stalldetails");
    $total_stmt->execute();
    $total_result = $total_stmt->get_result()->fetch_assoc();
    $total_stalls = $total_result['total'];

    // Approved stalls
    $approved_stmt = $conn->prepare("SELECT COUNT(*) AS approved FROM stalldetails WHERE approval = 1");
    $approved_stmt->execute();
    $approved_result = $approved_stmt->get_result()->fetch_assoc();
    $approved_stalls = $approved_result['approved'];

    // Rejected stalls
    $rejected_stmt = $conn->prepare("SELECT COUNT(*) AS rejected FROM stalldetails WHERE approval = -1");
    $rejected_stmt->execute();
    $rejected_result = $rejected_stmt->get_result()->fetch_assoc();
    $rejected_stalls = $rejected_result['rejected'];

    // Pending stalls
    $pending_stmt = $conn->prepare("SELECT COUNT(*) AS pending FROM stalldetails WHERE approval = 0");
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result()->fetch_assoc();
    $pending_stalls = $pending_result['pending'];

    echo json_encode([
        "status" => "success",
        "total_stalls" => (int)$total_stalls,
        "approved" => (int)$approved_stalls,
        "rejected" => (int)$rejected_stalls,
        "pending" => (int)$pending_stalls
    ]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "❌ Failed to fetch dashboard data: " . $e->getMessage()]);
}
?>
