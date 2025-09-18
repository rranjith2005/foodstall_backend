<?php
header('Content-Type: application/json');
include 'config.php';

$student_id = $_POST['student_id'] ?? '';
if (empty($student_id)) { /* handle error */ exit; }

$stmt = $conn->prepare(
    "SELECT s.stall_id, s.stallname, s.profile_photo, COALESCE(avg_ratings.avg_rating, 0.0) as rating
     FROM favorite_stalls f
     JOIN stalldetails s ON f.stall_id = s.stall_id
     LEFT JOIN (
         SELECT stall_id, AVG(rating) as avg_rating FROM student_reviews GROUP BY stall_id
     ) as avg_ratings ON s.stall_id = avg_ratings.stall_id
     WHERE f.student_id = ?"
);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['status' => 'success', 'favorites' => $result]);
?>