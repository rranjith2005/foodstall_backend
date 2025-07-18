<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

// Enable exception mode for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $fullname = $_POST['fullname'] ?? '';
    $phonenumber = $_POST['phonenumber'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Check if passwords match
    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert owner into Osignup table
    $stmt = $conn->prepare("INSERT INTO Osignup (fullname, phonenumber, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $fullname, $phonenumber, $hashed_password);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Owner registered successfully"]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    // Check for duplicate entry error code
    if ($e->getCode() == 1062) {
        echo json_encode(["status" => "error", "message" => "Owner with this phone number already existed"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed: " . $e->getMessage()]);
    }
}
?>
