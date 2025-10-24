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
    // [NEW] Get the signup type. Default to 'manual' if not provided.
    $signup_type = $_POST['signup_type'] ?? 'manual';

    // --- SERVER-SIDE VALIDATION ---
    if (empty($stallname) || empty($ownername) || empty($phonenumber) || empty($email) || empty($fulladdress) || empty($fssainumber)) {
        // Note: Password can be empty now for Google signups
        echo json_encode(["status" => "error", "message" => "All fields except password are required"]);
        exit;
    }

    if (!preg_match("/^[a-zA-Z\s]+$/", $stallname)) {
        echo json_encode(["status" => "error", "message" => "Stall name must contain only alphabets and spaces"]);
        exit;
    }

    if (!preg_match("/^[a-zA-Z\s]+$/", $ownername)) {
        echo json_encode(["status" => "error", "message" => "Owner name must contain only alphabets and spaces"]);
        exit;
    }

    if (!preg_match("/^[0-9]{10}$/", $phonenumber)) {
        echo json_encode(["status" => "error", "message" => "Phone number must be exactly 10 digits"]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format"]);
        exit;
    }

    if (substr($email, -10) !== '@gmail.com') {
        echo json_encode(["status" => "error", "message" => "Only @gmail.com emails are accepted"]);
        exit;
    }
    
    if (empty($fulladdress)) { // Simple check for address
        echo json_encode(["status" => "error", "message" => "Full address is required"]);
        exit;
    }

    if (!preg_match("/^[0-9]{14}$/", $fssainumber)) {
        echo json_encode(["status" => "error", "message" => "FSSAI Number must be exactly 14 digits"]);
        exit;
    }
    
    // --- [NEW] CONDITIONAL PASSWORD VALIDATION ---
    if ($signup_type === 'manual') {
        // Only run password checks for manual signups.
        
        if (empty($password)) {
             echo json_encode(["status" => "error", "message" => "Password is required for manual submission"]);
             exit;
        }
        
        // Password validation: 1 uppercase, 1 lowercase, 1 number, 1 special char, 6-8 length
        // [FIXED] Updated regex to match Java's PASSWORD_PATTERN
        $password_regex = "/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[@#$%^&+=!])(?=\S+$).{6,8}$/";
        if (!preg_match($password_regex, $password)) {
            echo json_encode(["status" => "error", "message" => "Password format is incorrect"]);
            exit;
        }
        
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
            // This is the specific error message you requested
            throw new Exception("Incorrect password. Please enter the password you used for signup.");
        }
        $stmt_owner->close();

    } else {
        // For 'google' signup_type, we skip validation.
        // We just need to link this new phone number to their Google account
        // which is identified by EMAIL.
        $stmt_link = $conn->prepare("UPDATE Osignup SET phonenumber = ? WHERE email = ?");
        if (!$stmt_link) {
            throw new Exception("Failed to prepare account link statement.");
        }
        $stmt_link->bind_param("ss", $phonenumber, $email);
        $stmt_link->execute();
        
        if ($stmt_link->affected_rows === 0) {
            // This means the Google email wasn't found, which shouldn't happen.
            throw new Exception("Could not find Google account. Please restart signup.");
        }
        $stmt_link->close();
    }
    // --- END OF CONDITIONAL VALIDATION ---


    // Check if a stall record already exists for this phone number
    $stmt_check = $conn->prepare("SELECT id FROM stalldetails WHERE phonenumber = ?");
    $stmt_check->bind_param("s", $phonenumber);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();

    if ($result_check->num_rows > 0) {
        // --- UPDATE EXISTING RECORD ---
        $stmt_update = $conn->prepare("UPDATE stalldetails SET stallname=?, ownername=?, email=?, fulladdress=?, fssainumber=?, approval=0, rejection_reason=NULL WHERE phonenumber=?");
        $stmt_update->bind_param("ssssss", $stallname, $ownername, $email, $fulladdress, $fssainumber, $phonenumber);
        $stmt_update->execute();
        $message = "Stall details updated and resubmitted for approval";
        $stmt_update->close();
    } else {
        // --- INSERT NEW RECORD ---
        $stmt_insert = $conn->prepare("INSERT INTO stalldetails (stallname, ownername, phonenumber, email, fulladdress, fssainumber, approval) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt_insert->bind_param("ssssss", $stallname, $ownername, $phonenumber, $email, $fulladdress, $fssainumber);
        $stmt_insert->execute();
        $message = "Stall details submitted for admin approval";
        $stmt_insert->close();
    }
    
    echo json_encode(["status" => "success", "message" => $message]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>