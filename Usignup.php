<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Auto-insert admin account if not exists
    $admin_email = 'admin123@gmail.com';
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $admin_check = $conn->prepare("SELECT * FROM Usignup WHERE email = ?");
    $admin_check->bind_param("s", $admin_email);
    $admin_check->execute();
    $admin_result = $admin_check->get_result();

    if ($admin_result->num_rows === 0) {
        $admin_fullname = 'admin';
        $admin_id = 'admin';
        $is_admin = 1;
        $admin_insert = $conn->prepare("INSERT INTO Usignup (fullname, student_id, email, password, is_admin) VALUES (?, ?, ?, ?, ?)");
        $admin_insert->bind_param("ssssi", $admin_fullname, $admin_id, $admin_email, $admin_password, $is_admin);
        $admin_insert->execute();
    }

    // Handle regular user signup
    $fullname = $_POST['fullname'] ?? '';
    $student_id = $_POST['id'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = 0;

    $stmt = $conn->prepare("INSERT INTO Usignup (fullname, student_id, email, password, is_admin) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $fullname, $student_id, $email, $hashed_password, $is_admin);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "Registered successfully"]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo json_encode(["status" => "error", "message" => "User already exists"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed: " . $e->getMessage()]);
    }
}
