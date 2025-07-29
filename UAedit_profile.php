<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

// Get POST data
$student_id = $_POST['student_id'] ?? '';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$phonenumber = $_POST['phonenumber'] ?? '';

// Validate required fields
if (empty($student_id) || empty($fullname) || empty($email) || empty($password) || empty($phonenumber)) {
    echo json_encode(["status" => "error", "message" => "All fields except profile_photo are required"]);
    exit;
}

// Validate phone number
if (!preg_match('/^[0-9]{10}$/', $phonenumber)) {
    echo json_encode(["status" => "error", "message" => "Phone number must be exactly 10 digits"]);
    exit;
}

// Handle profile photo upload
$profile_photo_url = '';
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name = basename($_FILES["profile_photo"]["name"]);
    $target_file = $target_dir . uniqid("profile_") . "_" . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_types = ["jpg", "jpeg", "png", "gif"];

    if (in_array($imageFileType, $allowed_types)) {
        if (!move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            echo json_encode(["status" => "error", "message" => "Failed to upload profile photo"]);
            exit;
        }
        $profile_photo_url = $target_file;
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid image type"]);
        exit;
    }
}

// Prepare update query
if (!empty($profile_photo_url)) {
    $stmt = $conn->prepare("UPDATE usignup SET fullname = ?, email = ?, password = ?, phonenumber = ?, profile_photo = ? WHERE student_id = ?");
    $stmt->bind_param("ssssss", $fullname, $email, $password, $phonenumber, $profile_photo_url, $student_id);
} else {
    $stmt = $conn->prepare("UPDATE usignup SET fullname = ?, email = ?, password = ?, phonenumber = ? WHERE student_id = ?");
    $stmt->bind_param("sssss", $fullname, $email, $password, $phonenumber, $student_id);
}

// Execute and respond
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update profile"]);
}
?>
