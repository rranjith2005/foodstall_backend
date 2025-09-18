<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';

if (empty($stall_id) || empty($student_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Stall ID and Student ID are required to delete a review.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM student_reviews WHERE stall_id = ? AND student_id = ?");
    $stmt->bind_param("ss", $stall_id, $student_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Review deleted successfully.']);
    } else {
        throw new Exception("Review not found or you do not have permission to delete it.");
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>