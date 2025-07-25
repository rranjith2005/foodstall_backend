<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_POST['stall_id'] ?? '';
$review_type = $_POST['review_type'] ?? '';
$review_text = $_POST['review_text'] ?? '';

// Validate inputs
if (empty($stall_id) || empty($review_type)) {
    echo json_encode(["status" => "error", "message" => "stall_id and review_type are required"]);
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

    // Check if review already exists
    $check_review = $conn->prepare("SELECT * FROM owner_reviews WHERE stall_id = ? AND review_type = ?");
    $check_review->bind_param("ss", $stall_id, $review_type);
    $check_review->execute();
    $result_review = $check_review->get_result();

    if ($result_review->num_rows > 0) {
        // Update existing review
        $update = $conn->prepare("UPDATE owner_reviews SET review_text = ?, review_date = CURRENT_DATE WHERE stall_id = ? AND review_type = ?");
        $update->bind_param("sss", $review_text, $stall_id, $review_type);
        $update->execute();

        echo json_encode(["status" => "success", "message" => "review updated"]);
    } else {
        // Insert new review
        $insert = $conn->prepare("INSERT INTO owner_reviews (stall_id, review_type, review_text, review_date) VALUES (?, ?, ?, CURRENT_DATE)");
        $insert->bind_param("sss", $stall_id, $review_type, $review_text);
        $insert->execute();

        echo json_encode(["status" => "success", "message" => "review inserted"]);
    }

    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to submit review: " . $e->getMessage()]);
}
?>
