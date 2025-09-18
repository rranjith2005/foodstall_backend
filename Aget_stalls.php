<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get filter from the POST request, default to 'all' if not provided
    $filter = $_POST['filter'] ?? 'all';

    // Base query
    $query = "SELECT * FROM StallDetails";
    $params = [];
    $types = '';

    // --- THIS IS THE CRITICAL FIX ---
    // We build the query dynamically and safely based on the filter.
    if ($filter == 'approved') {
        $query .= " WHERE approval = ?";
        $params[] = 1;
        $types .= 'i';
    } elseif ($filter == 'rejected') {
        $query .= " WHERE approval = ?";
        $params[] = -1;
        $types .= 'i';
    } elseif ($filter == 'pending') {
        $query .= " WHERE approval = ?";
        $params[] = 0;
        $types .= 'i';
    }

    $stmt = $conn->prepare($query);
    
    // Bind the parameter if a filter was applied
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stalls = [];
    while ($row = $result->fetch_assoc()) {
        $stalls[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "stalls" => $stalls
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>