<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get all POST data
    $stallname = $_POST['stallname'] ?? '';
    $ownername = $_POST['ownername'] ?? '';
    $phonenumber = $_POST['phonenumber'] ?? '';
    $email = $_POST['email'] ?? '';
    $fulladdress = $_POST['fulladdress'] ?? '';
    $fssainumber = $_POST['fssainumber'] ?? '';
    $password = $_POST['password'] ?? '';

    // Verify the owner and their password
    $stmt_owner = $conn->prepare("SELECT password FROM Osignup WHERE phonenumber = ?");
    $stmt_owner->bind_param("s", $phonenumber);
    $stmt_owner->execute();
    $result_owner = $stmt_owner->get_result();

    if ($result_owner->num_rows === 0) {
        throw new Exception("This phone number is not registered as an owner.");
    }
    $owner = $result_owner->fetch_assoc();
    if (!password_verify($password, $owner['password'])) {
        throw new Exception("Incorrect password. Please enter the password you used for signup.");
    }

    // --- THIS IS THE NEW LOGIC ---
    // Check if a stall record already exists for this phone number
    $stmt_check = $conn->prepare("SELECT id FROM stalldetails WHERE phonenumber = ?");
    $stmt_check->bind_param("s", $phonenumber);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // --- UPDATE EXISTING RECORD ---
        // The stall was rejected before, so we update it and set status back to pending (0)
        $stmt_update = $conn->prepare("UPDATE stalldetails SET stallname=?, ownername=?, email=?, fulladdress=?, fssainumber=?, approval=0, rejection_reason=NULL WHERE phonenumber=?");
        $stmt_update->bind_param("ssssss", $stallname, $ownername, $email, $fulladdress, $fssainumber, $phonenumber);
        $stmt_update->execute();
        $message = "Stall details updated and resubmitted for approval";
    } else {
        // --- INSERT NEW RECORD ---
        // This is a brand new submission
        $stmt_insert = $conn->prepare("INSERT INTO stalldetails (stallname, ownername, phonenumber, email, fulladdress, fssainumber, approval) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt_insert->bind_param("ssssss", $stallname, $ownername, $phonenumber, $email, $fulladdress, $fssainumber);
        $stmt_insert->execute();
        $message = "Stall details submitted for admin approval";
    }
    
    echo json_encode(["status" => "success", "message" => $message]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>