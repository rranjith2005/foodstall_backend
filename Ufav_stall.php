<?php
include 'config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        // DELETE favorite
        $student_id = $_POST['student_id'] ?? '';
        $stall_id = $_POST['stall_id'] ?? '';

        if (empty($student_id) || empty($stall_id)) {
            echo json_encode(["status" => "error", "message" => "student_id and stall_id are required"]);
            exit;
        }

        // Check existence
        $check = $conn->prepare("SELECT * FROM favouritestalls WHERE student_id = ? AND stall_id = ?");
        $check->bind_param("ss", $student_id, $stall_id);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Favorite not found or already removed"]);
            exit;
        }

        // Delete
        $delete = $conn->prepare("DELETE FROM favouritestalls WHERE student_id = ? AND stall_id = ?");
        $delete->bind_param("ss", $student_id, $stall_id);
        if ($delete->execute()) {
            echo json_encode(["status" => "success", "message" => "Favorite removed"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to remove favorite"]);
        }

    } else {
        // ADD favorite
        $student_id = $_POST['student_id'] ?? '';
        $stall_id = $_POST['stall_id'] ?? '';

        if (empty($student_id) || empty($stall_id)) {
            echo json_encode(["status" => "error", "message" => "student_id and stall_id are required"]);
            exit;
        }

        // Check if already exists
        $check = $conn->prepare("SELECT * FROM favouritestalls WHERE student_id = ? AND stall_id = ?");
        $check->bind_param("ss", $student_id, $stall_id);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "Favorite already exists"]);
            exit;
        }

        // Insert
        $insert = $conn->prepare("INSERT INTO favouritestalls (student_id, stall_id, added_on) VALUES (?, ?, NOW())");
        $insert->bind_param("ss", $student_id, $stall_id);
        if ($insert->execute()) {
            echo json_encode(["status" => "success", "message" => "Favorite added"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to add favorite"]);
        }
    }

} elseif ($method === 'GET') {
    // GET favorite stalls
    $student_id = $_GET['student_id'] ?? '';

    if (empty($student_id)) {
        echo json_encode(["status" => "error", "message" => "student_id is required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT stall_id FROM favouritestalls WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $stalls = [];
    while ($row = $result->fetch_assoc()) {
        $stalls[] = $row['stall_id'];
    }

    echo json_encode([
        "status" => "success",
        "favouritestalls" => $stalls
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
?>
