<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $id = $_POST['id'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($id) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "ID and password are required"]);
        exit;
    }

    // 1. Check Admin table (username as id)
    $stmt = $conn->prepare("SELECT * FROM Admin WHERE username = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            echo json_encode([
                "status" => "success",
                "role" => "admin",
                "message" => "Admin login successful",
                "admin" => [
                    "id" => $admin['id'],
                    "username" => $admin['username']
                ]
            ]);
            exit;
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid password"]);
            exit;
        }
    }

    // 2. Check Owner table (stall_id as id)
    $stmt = $conn->prepare("SELECT * FROM StallDetails WHERE stall_id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $stall = $result->fetch_assoc();

        if ($stall['approval'] == 1) {
            // Get owner password from Osignup using phonenumber
            $phonenumber = $stall['phonenumber'];
            $stmt2 = $conn->prepare("SELECT * FROM Osignup WHERE phonenumber = ?");
            $stmt2->bind_param("s", $phonenumber);
            $stmt2->execute();
            $result2 = $stmt2->get_result();

            if ($result2->num_rows === 1) {
                $owner = $result2->fetch_assoc();
                if (password_verify($password, $owner['password'])) {
echo json_encode([
    "status" => "success",
    "role" => "owner",
    "message" => "Owner login successful",
    "owner" => [
        "id" => $owner['id'],
        "fullname" => $owner['fullname'],
        "phonenumber" => $owner['phonenumber'],
        "email" => $stall['email'], // get from StallDetails
        "stall_id" => $stall['stall_id']
    ]
]);

                    exit;
                } else {
                    echo json_encode(["status" => "error", "message" => "Invalid password"]);
                    exit;
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Owner details not found"]);
                exit;
            }

        } else {
            echo json_encode(["status" => "error", "message" => "Your stall is not yet approved by admin"]);
            exit;
        }
    }

    // 3. Check Student table (student_id as id)
    $stmt = $conn->prepare("SELECT * FROM Usignup WHERE student_id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $student = $result->fetch_assoc();
        if (password_verify($password, $student['password'])) {
            echo json_encode([
                "status" => "success",
                "role" => "student",
                "message" => "Student login successful",
                "student" => [
                    "id" => $student['id'],
                    "fullname" => $student['fullname'],
                    "student_id" => $student['student_id'],
                    "email" => $student['email']
                ]
            ]);
            exit;
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid password"]);
            exit;
        }
    }

    // If no user found
    echo json_encode(["status" => "error", "message" => "User not found"]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Login failed: " . $e->getMessage()]);
}
?>
