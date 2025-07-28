<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

include 'config.php';

// Get input values
$stall_id      = $_POST['stall_id'] ?? '';
$stallname     = $_POST['stallname'] ?? '';
$ownername     = $_POST['ownername'] ?? '';
$fulladdress   = $_POST['fulladdress'] ?? '';
$phonenumber   = $_POST['phonenumber'] ?? '';
$email         = $_POST['email'] ?? '';
$password      = $_POST['password'] ?? '';

$stall_id = $_POST['stall_id'] ?? '';
$phonenumber = $_POST['phonenumber'] ?? '';

// Step 1: Check if stall_id is provided
if (empty($stall_id)) {
    echo json_encode(["status" => "error", "message" => "❌ Stall ID is required"]);
    exit;
}

// ✅ Step 2: Validate phone number format
if (!preg_match('/^[0-9]{10}$/', $phonenumber)) {
    echo json_encode(["status" => "error", "message" => "❌ Invalid phone number. It must be 10 digits."]);
    exit;
}



// Check if stall exists
$check = $conn->prepare("SELECT * FROM stalldetails WHERE stall_id = ?");
$check->bind_param("s", $stall_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "❌ Stall ID not found"]);
    exit;
}

// Handle password hashing
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Handle profile photo upload
$profilePhotoPath = null;
if (isset($_FILES['profilephoto']) && $_FILES['profilephoto']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/profilephotos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmp = $_FILES['profilephoto']['tmp_name'];
    $fileName = basename($_FILES['profilephoto']['name']);
    $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = uniqid("profile_", true) . "." . $fileExt;
    $destination = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $destination)) {
        $profilePhotoPath = $destination;
    } else {
        echo json_encode(["status" => "error", "message" => "❌ Failed to upload profile photo"]);
        exit;
    }
}

// Prepare update query with optional photo
if ($profilePhotoPath) {
    $sql = "UPDATE stalldetails SET 
                stallname = ?, 
                ownername = ?, 
                fulladdress = ?, 
                phonenumber = ?, 
                email = ?, 
                password = ?, 
                profilephoto = ?
            WHERE stall_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $stallname, $ownername, $fulladdress, $phonenumber, $email, $hashedPassword, $profilePhotoPath, $stall_id);
} else {
    $sql = "UPDATE stalldetails SET 
                stallname = ?, 
                ownername = ?, 
                fulladdress = ?, 
                phonenumber = ?, 
                email = ?, 
                password = ?
            WHERE stall_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $stallname, $ownername, $fulladdress, $phonenumber, $email, $hashedPassword, $stall_id);
}

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "✅ Profile updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "❌ Failed to update profile"]);
}
?>

