<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $counts = [];

    // Query for each status count
    $stmt_total = $conn->query("SELECT COUNT(*) as total FROM stalldetails");
    $counts['total_stalls'] = $stmt_total->fetch_assoc()['total'] ?? 0;

    $stmt_approved = $conn->query("SELECT COUNT(*) as total FROM stalldetails WHERE approval = 1");
    $counts['approved_stalls'] = $stmt_approved->fetch_assoc()['total'] ?? 0;

    $stmt_rejected = $conn->query("SELECT COUNT(*) as total FROM stalldetails WHERE approval = -1");
    $counts['rejected_stalls'] = $stmt_rejected->fetch_assoc()['total'] ?? 0;

    $stmt_pending = $conn->query("SELECT COUNT(*) as total FROM stalldetails WHERE approval = 0");
    $counts['pending_stalls'] = $stmt_pending->fetch_assoc()['total'] ?? 0;

    echo json_encode(['status' => 'success', 'data' => $counts]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}

$conn->close();
?>