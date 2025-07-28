<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $stallname = $_POST['stallname'] ?? '';
    $ownername = $_POST['ownername'] ?? '';
    $phonenumber = $_POST['phonenumber'] ?? '';
    $email = $_POST['email'] ?? '';
    $fulladdress = $_POST['fulladdress'] ?? '';
    $fssainumber = $_POST['fssainumber'] ?? '';

    // Validate phone number (10 digits)
    if (!preg_match('/^[0-9]{10}$/', $phonenumber)) {
        echo json_encode(["status" => "error", "message" => "Phone number must be exactly 10 digits"]);
        exit;
    }

    // Validate FSSAI number (14 digits)
    if (!preg_match('/^[0-9]{14}$/', $fssainumber)) {
        echo json_encode(["status" => "error", "message" => "FSSAI number must be exactly 14 digits"]);
        exit;
    }

    // Check if phonenumber exists in Osignup (owner signup table)
    $check_owner = $conn->prepare("SELECT * FROM Osignup WHERE phonenumber = ?");
    $check_owner->bind_param("s", $phonenumber);
    $check_owner->execute();
    $owner_result = $check_owner->get_result();

    if ($owner_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Phone number not registered as owner"]);
        exit;
    }

    // Insert stall details with approval=0
    $stmt = $conn->prepare("INSERT INTO StallDetails (stallname, ownername, phonenumber, email, fulladdress, fssainumber, approval) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("ssssss", $stallname, $ownername, $phonenumber, $email, $fulladdress, $fssainumber);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Stall details submitted for admin approval"]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        if (strpos($e->getMessage(), 'phonenumber') !== false) {
            echo json_encode(["status" => "error", "message" => "Phone number already exists in stall records"]);
        } elseif (strpos($e->getMessage(), 'fssainumber') !== false) {
            echo json_encode(["status" => "error", "message" => "FSSAI number already exists"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Duplicate entry"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed: " . $e->getMessage()]);
    }
}
?>
