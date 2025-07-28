<?php
header('Content-Type: application/json');
include 'config.php';

$stall_id = $_GET['stall_id'] ?? '';

if (empty($stall_id)) {
    echo json_encode(["status" => "error", "message" => "stall_id is required"]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM owner_reviews WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = [
            "review_id" => $row['review_id'],
            "review_type" => $row['review_type'],
            "review_text" => $row['review_text'],
            "review_date" => $row['review_date']
        ];
    }

    echo json_encode(["status" => "success", "reviews" => $reviews]);

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to fetch owner reviews: " . $e->getMessage()]);
}
?>
