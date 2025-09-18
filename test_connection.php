<?php
// Turn on all error reporting for this test file
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

// --- VERIFY THESE DETAILS ---
$host = "localhost"; 
$user = "root";
$password = "12345";
$db = "foodstall"; // <-- Is your database name EXACTLY "foodstall"? Check for typos.
// -------------------------

echo "<p>Attempting to connect...</p>";

// Create connection
$conn = new mysqli($host, $user, $password, $db);

// Check connection
if ($conn->connect_error) {
    echo "<h2>CONNECTION FAILED!</h2>";
    // This will print the exact error message from MySQL
    die("<p style='color:red;'>Error: " . $conn->connect_error . "</p>");
} else {
    echo "<h2>CONNECTION SUCCESSFUL!</h2>";
    echo "<p style='color:green;'>You are correctly connected to the database.</p>";
}

$conn->close();
?>