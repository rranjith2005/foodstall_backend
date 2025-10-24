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

    // --- SERVER-SIDE VALIDATION ---
    if (empty($fullname) || empty($phonenumber) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }

    if (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        echo json_encode(["status" => "error", "message" => "Full name must contain only alphabets and spaces"]);
        exit;
    }

    if (!preg_match("/^[0-9]{10}$/", $phonenumber)) {
        echo json_encode(["status" => "error", "message" => "Phone number must be exactly 10 digits"]);
        exit;
    }

    // Password validation: 1 uppercase, 1 lowercase, 1 number, 1 special char, 6-8 length
    $password_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,8}$/";
    if (!preg_match($password_regex, $password)) {
        echo json_encode(["status" => "error", "message" => "Password does not meet requirements"]);
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
        exit;
    }

    // --- CHECK FOR DUPLICATES (Proactive Check) ---
    $stmt_check = $conn->prepare("SELECT id FROM Osignup WHERE phonenumber = ?");
    $stmt_check->bind_param("s", $phonenumber);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "An owner with this phone number already exists"]);
        $stmt_check->close();
        $conn->close();
        exit;
    }
    $stmt_check->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert owner into Osignup table
    $stmt_insert = $conn->prepare("INSERT INTO Osignup (fullname, phonenumber, password) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("sss", $fullname, $phonenumber, $hashed_password);
    
    if ($stmt_insert->execute()) {
        echo json_encode(["status" => "success", "message" => "Owner registered! Please add your stall details."]);
    } else {
        // This case might be redundant due to try/catch but is good for clarity
        echo json_encode(["status" => "error", "message" => "Registration failed. Please try again."]);
    }

    $stmt_insert->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    // Fallback for race conditions or other DB errors
    if ($e->getCode() == 1062) {
        echo json_encode(["status" => "error", "message" => "An owner with this phone number already exists."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
}
?>