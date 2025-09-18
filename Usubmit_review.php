<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get data from the app
$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$rating = $_POST['rating'] ?? 0;
$review_text = $_POST['review_text'] ?? '';

if (empty($stall_id) || empty($student_id) || $rating == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields are missing.']);
    exit;
}

try {
    // A student can only review a stall once. 
    // This query will INSERT a new review, or UPDATE their existing one if they review again.
    $stmt = $conn->prepare(
        "INSERT INTO student_reviews (stall_id, student_id, rating, review_text, review_date) 
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE 
         rating = VALUES(rating), 
         review_text = VALUES(review_text), 
         review_date = NOW()"
    );

    $stmt->bind_param("ssds", $stall_id, $student_id, $rating, $review_text);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Review submitted successfully!']);
    } else {
        throw new Exception("Failed to save review.");
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>