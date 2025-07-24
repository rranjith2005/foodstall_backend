<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$rating = $_POST['rating'] ?? '';
$review_text = $_POST['review_text'] ?? '';

if (empty($stall_id) || empty($student_id) || empty($rating)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

try {
    // Check if stall exists and is approved
    $stmt = $conn->prepare("SELECT approval FROM stalldetails WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Invalid stall_id"]);
        exit;
    }

    $stall = $result->fetch_assoc();
    if ($stall['approval'] != 1) {
        echo json_encode(["status" => "error", "message" => "Stall is not approved"]);
        exit;
    }

    // Check if student exists
    $stmt = $conn->prepare("SELECT student_id FROM Usignup WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Invalid student_id"]);
        exit;
    }

    // Insert review with date
    $today_date = date('Y-m-d');
    $stmt = $conn->prepare("INSERT INTO student_reviews (stall_id, student_id, rating, review_text, review_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $stall_id, $student_id, $rating, $review_text, $today_date);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Review submitted successfully"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to submit review: " . $e->getMessage()]);
}
?>
