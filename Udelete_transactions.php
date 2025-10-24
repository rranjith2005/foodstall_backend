<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$student_id = $_POST['student_id'] ?? '';
$transaction_ids_json = $_POST['transaction_ids_json'] ?? '';

if (empty($student_id) || empty($transaction_ids_json)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Student ID and Transaction IDs are required.']));
}

$transaction_ids = json_decode($transaction_ids_json, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($transaction_ids) || empty($transaction_ids)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid Transaction IDs format.']));
}

try {
    // Create placeholders for the IN clause (e.g., ?,?,?)
    $placeholders = implode(',', array_fill(0, count($transaction_ids), '?'));
    
    // Define the data types for binding (s for student_id, followed by 'i' for each integer transaction_id)
    $types = 's' . str_repeat('i', count($transaction_ids));
    
    // The query ensures users can only delete their own transactions
    $sql = "DELETE FROM wallet_transactions WHERE student_id = ? AND transaction_id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, $student_id, ...$transaction_ids);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Transactions deleted.']);
    } else {
        throw new Exception('No transactions found to delete.');
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>  