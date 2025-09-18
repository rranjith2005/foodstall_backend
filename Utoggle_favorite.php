<?php
header('Content-Type: application/json');
include 'config.php';

$student_id = $_POST['student_id'] ?? '';
$stall_id = $_POST['stall_id'] ?? '';

if (empty($student_id) || empty($stall_id)) { /* handle error */ exit; }

// Check if the favorite already exists
$stmt_check = $conn->prepare("SELECT favorite_id FROM favorite_stalls WHERE student_id = ? AND stall_id = ?");
$stmt_check->bind_param("ss", $student_id, $stall_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    // It exists, so DELETE it (unfavorite)
    $stmt_delete = $conn->prepare("DELETE FROM favorite_stalls WHERE student_id = ? AND stall_id = ?");
    $stmt_delete->bind_param("ss", $student_id, $stall_id);
    $stmt_delete->execute();
    echo json_encode(['status' => 'success', 'action' => 'removed', 'message' => 'Removed from favorites.']);
} else {
    // It does not exist, so INSERT it (favorite)
    $stmt_insert = $conn->prepare("INSERT INTO favorite_stalls (student_id, stall_id) VALUES (?, ?)");
    $stmt_insert->bind_param("ss", $student_id, $stall_id);
    $stmt_insert->execute();
    echo json_encode(['status' => 'success', 'action' => 'added', 'message' => 'Added to favorites!']);
}
?>