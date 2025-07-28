<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_GET['stall_id'] ?? '';

if (empty($stall_id)) {
    echo json_encode(["status" => "error", "message" => "stall_id is required"]);
    exit;
}

try {
    // ✅ Sorted by most recent review_date (descending)
    $stmt = $conn->prepare("SELECT * FROM student_reviews WHERE stall_id = ? ORDER BY review_date DESC");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            "review_id" => $row['review_id'],
            "student_id" => $row['student_id'],
            "rating" => $row['rating'],
            "review_text" => $row['review_text'],
            "review_date" => $row['review_date']
        ];
    }

    echo json_encode(["status" => "success", "reviews" => $reviews]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to fetch student reviews: " . $e->getMessage()]);
}
?>
