<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

// Enable exception mode for mysqli
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

    // Insert stall details into StallDetails table
    $stmt = $conn->prepare("INSERT INTO StallDetails (stallname, ownername, phonenumber, email, fulladdress, fssainumber) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $stallname, $ownername, $phonenumber, $email, $fulladdress, $fssainumber);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Stall details registered successfully"]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    // Check for duplicate entry
    if ($e->getCode() == 1062) {
        if (strpos($e->getMessage(), 'phonenumber') !== false) {
            echo json_encode(["status" => "error", "message" => "Phone number already exists"]);
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
