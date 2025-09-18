<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php'; // Your database connection file

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];

try {
    // --- PART 1: AUTO-INSERT ADMIN (Your existing logic) ---
    $admin_email = 'admin123@gmail.com';
    $admin_password_to_hash = 'admin123';
    
    $admin_check = $conn->prepare("SELECT id FROM Usignup WHERE email = ?");
    $admin_check->bind_param("s", $admin_email);
    $admin_check->execute();
    $admin_result = $admin_check->get_result();

    if ($admin_result->num_rows === 0) {
        $admin_fullname = 'admin';
        $admin_id = 'admin';
        $is_admin = 1;
        $admin_hashed_password = password_hash($admin_password_to_hash, PASSWORD_DEFAULT);
        
        $admin_insert = $conn->prepare("INSERT INTO Usignup (fullname, student_id, email, password, is_admin) VALUES (?, ?, ?, ?, ?)");
        $admin_insert->bind_param("ssssi", $admin_fullname, $admin_id, $admin_email, $admin_hashed_password, $is_admin);
        $admin_insert->execute();
        $admin_insert->close();
    }
    $admin_check->close();

    // --- PART 2: HANDLE REGULAR USER SIGNUP ---
    $fullname = $_POST['fullname'] ?? '';
    $student_id = $_POST['id'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- STEP A: VALIDATE INPUT ---
    // This block prevents the script from proceeding if the app sends empty data.
    if (empty($fullname) || empty($student_id) || empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "All fields are required"]);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
        exit;
    }
    
    // --- STEP B: CHECK FOR DUPLICATES ---
    // This prevents the script from crashing if the user already exists.
    $stmt = $conn->prepare("SELECT id FROM Usignup WHERE email = ? OR student_id = ?");
    $stmt->bind_param("ss", $email, $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "An account with this email or Student ID already exists"]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // --- STEP C: INSERT NEW USER ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = 0; // Regular users are not admins

    $insert_stmt = $conn->prepare("INSERT INTO Usignup (fullname, student_id, email, password, is_admin) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssssi", $fullname, $student_id, $email, $hashed_password, $is_admin);
    
    if ($insert_stmt->execute()) {
        $response = ["status" => "success", "message" => "Registered successfully"];
    } else {
        $response = ["status" => "error", "message" => "Registration failed. Please try again."];
    }
    
    $insert_stmt->close();

} catch (mysqli_sql_exception $e) {
    $response = ["status" => "error", "message" => "Database error: " . $e->getMessage()];
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    if (!empty($response)) {
        echo json_encode($response);
    }
}
?>