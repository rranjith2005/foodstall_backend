<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

$response = [];

// Common POST data
$role = $_POST['role'] ?? '';
$id = $_POST['id'] ?? ''; // This will be student_id or stall_id

try {
    $photo_filename_to_update = null;

    // --- Part 1: Handle File Upload if a photo is sent ---
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_dir = 'uploads/';
        $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid($role . '_' . $id . '_', true) . '.' . $file_extension;
        $target_file = $upload_dir . $unique_filename;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            $photo_filename_to_update = $unique_filename;
        } else {
            throw new Exception("Failed to save the uploaded file.");
        }
    }

    // --- Part 2: Update Database based on Role ---
    switch ($role) {
        case 'user':
        case 'admin':
            $fullname = $_POST['fullname'] ?? '';
            $email = $_POST['email'] ?? '';
            $phonenumber = $_POST['phonenumber'] ?? '';

            if ($photo_filename_to_update) {
                $stmt = $conn->prepare("UPDATE usignup SET fullname = ?, email = ?, phonenumber = ?, profile_photo = ? WHERE student_id = ?");
                $stmt->bind_param("sssss", $fullname, $email, $phonenumber, $photo_filename_to_update, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usignup SET fullname = ?, email = ?, phonenumber = ? WHERE student_id = ?");
                $stmt->bind_param("ssss", $fullname, $email, $phonenumber, $id);
            }
            $stmt->execute();
            $stmt->close();
            break;

        case 'owner':
            $ownername = $_POST['ownername'] ?? '';
            $phonenumber = $_POST['phonenumber'] ?? '';
            $email = $_POST['email'] ?? '';
            $fulladdress = $_POST['fulladdress'] ?? '';
            
            // Update stalldetails table
            if ($photo_filename_to_update) {
                $stmt_stall = $conn->prepare("UPDATE stalldetails SET ownername = ?, phonenumber = ?, email = ?, fulladdress = ?, profile_photo = ? WHERE stall_id = ?");
                $stmt_stall->bind_param("ssssss", $ownername, $phonenumber, $email, $fulladdress, $photo_filename_to_update, $id);
            } else {
                $stmt_stall = $conn->prepare("UPDATE stalldetails SET ownername = ?, phonenumber = ?, email = ?, fulladdress = ? WHERE stall_id = ?");
                $stmt_stall->bind_param("sssss", $ownername, $phonenumber, $email, $fulladdress, $id);
            }
            $stmt_stall->execute();
            $stmt_stall->close();

            // Update Osignup table for consistency
            $stmt_osignup = $conn->prepare("UPDATE Osignup SET fullname = ?, phonenumber = ? WHERE phonenumber = (SELECT phonenumber FROM stalldetails WHERE stall_id = ? LIMIT 1)");
            $stmt_osignup->bind_param("sss", $ownername, $phonenumber, $id);
            $stmt_osignup->execute();
            $stmt_osignup->close();
            break;

        default:
            throw new Exception("Invalid role specified.");
    }
    
    $conn->commit();
    $response = ['status' => 'success', 'message' => 'Profile updated successfully'];

} catch (Exception $e) {
    $conn->rollback();
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>