<?php
$host = "localhost";    
$user = "root";
$password = "12345";  // ✅ Your MySQL root password
$db = "foodstall";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
