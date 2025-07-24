<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_POST['stall_id'] ?? '';
$review_type = $_POST['review_type'] ?? '';
$review_text = $_POST['review_text'] ?? '';

if (empty($stall_id) || empty($review_type) || empty($review_text)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

try {
    // Verify stall_id exists in stalldetails
    $stmt = $conn->prepare("SELECT stall_id FROM stalldetails WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Invalid stall_id"]);
        exit;
    }

    // Insert review
    $stmt = $conn->prepare("INSERT INTO owner_reviews (stall_id, review_type, review_text) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $stall_id, $review_type, $review_text);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Owner review submitted successfully"]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to submit owner review: " . $e->getMessage()]);
}
?>
