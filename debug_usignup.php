<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include 'config.php'; // Includes your database connection
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    echo json_encode(["status" => "debug", "message" => "Connection successful. Now testing the query..."]);

    // This is the exact query from your Usignup.php.
    // We are testing if the table and column names are correct.
    $query = "INSERT INTO Usignup (fullname, student_id, email, password, is_admin) VALUES (?, ?, ?, ?, ?)";
    
    // The prepare() function will fail if the table or a column is misspelled.
    $stmt = $conn->prepare($query);
    
    // If we reach this line, the query is valid.
    echo json_encode(["status" => "success", "message" => "Database query is valid."]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // If the prepare() function failed, this block will run and show the exact error.
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "QUERY FAILED! Error: " . $e->getMessage()
    ]);
}
?>