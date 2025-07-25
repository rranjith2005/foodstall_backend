<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$rating = $_POST['rating'] ?? '';
$review_text = $_POST['review_text'] ?? '';

// Validate inputs
if (empty($stall_id) || empty($student_id) || empty($rating)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

try {
    // Check if stall exists and is approved
    $check_stall = $conn->prepare("SELECT * FROM stalldetails WHERE stall_id = ? AND approval = 1");
    $check_stall->bind_param("s", $stall_id);
    $check_stall->execute();
    $result_stall = $check_stall->get_result();

    if ($result_stall->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Invalid or unapproved stall_id"]);
        exit;
    }

    // Check if student exists
    $check_student = $conn->prepare("SELECT * FROM Usignup WHERE student_id = ?");
    $check_student->bind_param("s", $student_id);
    $check_student->execute();
    $result_student = $check_student->get_result();

    if ($result_student->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Invalid student_id"]);
        exit;
    }

    // Check if review already exists
    $check_review = $conn->prepare("SELECT * FROM student_reviews WHERE stall_id = ? AND student_id = ?");
    $check_review->bind_param("ss", $stall_id, $student_id);
    $check_review->execute();
    $result_review = $check_review->get_result();

    if ($result_review->num_rows > 0) {
        // Update existing review
        $update = $conn->prepare("UPDATE student_reviews SET rating = ?, review_text = ?, review_date = CURRENT_DATE WHERE stall_id = ? AND student_id = ?");
        $update->bind_param("ssss", $rating, $review_text, $stall_id, $student_id);
        $update->execute();

        echo json_encode(["status" => "success", "message" => "review updated"]);
    } else {
        // Insert new review
        $insert = $conn->prepare("INSERT INTO student_reviews (stall_id, student_id, rating, review_text, review_date) VALUES (?, ?, ?, ?, CURRENT_DATE)");
        $insert->bind_param("ssss", $stall_id, $student_id, $rating, $review_text);
        $insert->execute();

        echo json_encode(["status" => "success", "message" => "review inserted"]);
    }

    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to submit review: " . $e->getMessage()]);
}
?>
