<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction(); // Start a transaction

$response = [];
$stall_id = $_POST['stall_id'] ?? '';
$ownername = $_POST['ownername'] ?? '';
$phonenumber = $_POST['phonenumber'] ?? '';
$email = $_POST['email'] ?? '';
$fulladdress = $_POST['fulladdress'] ?? '';

if (empty($stall_id) || empty($ownername) || empty($phonenumber) || empty($email) || empty($fulladdress)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit;
}

// --- SERVER-SIDE VALIDATION ---
if (!preg_match("/^[a-zA-Z\s]+$/", $ownername)) {
    echo json_encode(["status" => "error", "message" => "Owner name must contain only alphabets and spaces"]);
    exit;
}

if (!preg_match("/^[0-9]{10}$/", $phonenumber)) {
    echo json_encode(["status" => "error", "message" => "Phone number must be exactly 10 digits"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email format"]);
    exit;
}
if (substr($email, -10) !== '@gmail.com') {
    echo json_encode(['status' => 'error', 'message' => 'Only @gmail.com emails are accepted']);
    exit;
}
// --- END OF VALIDATION ---

try {
    // 1. Get the owner's Osignup ID and old phone number using the stall_id
    $stmt_find = $conn->prepare("SELECT o.id, o.phonenumber AS old_phone FROM Osignup o JOIN stalldetails s ON o.phonenumber = s.phonenumber WHERE s.stall_id = ?");
    if (!$stmt_find) throw new Exception("Prepare failed (find): " . $conn->error);
    $stmt_find->bind_param("s", $stall_id);
    $stmt_find->execute();
    $result_find = $stmt_find->get_result();
    
    if ($result_find->num_rows === 0) {
        throw new Exception("Critical error: Owner linkage not found.");
    }
    $owner_link = $result_find->fetch_assoc();
    $owner_id = $owner_link['id'];
    $old_phone = $owner_link['old_phone'];
    $stmt_find->close();

    // 2. Check if the new phone number is already used by ANOTHER owner
    $stmt_check = $conn->prepare("SELECT id FROM Osignup WHERE phonenumber = ? AND id != ?");
    if (!$stmt_check) throw new Exception("Prepare failed (check): " . $conn->error);
    $stmt_check->bind_param("si", $phonenumber, $owner_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("This phone number is already registered to another owner.");
    }
    $stmt_check->close();

    // 3. Update stalldetails table
    $stmt_update_stall = $conn->prepare("UPDATE stalldetails SET ownername = ?, phonenumber = ?, email = ?, fulladdress = ? WHERE stall_id = ?");
    if (!$stmt_update_stall) throw new Exception("Prepare failed (update stall): " . $conn->error);
    $stmt_update_stall->bind_param("sssss", $ownername, $phonenumber, $email, $fulladdress, $stall_id);
    $stmt_update_stall->execute();
    $stmt_update_stall->close();

    // 4. Update Osignup table
    $stmt_update_osignup = $conn->prepare("UPDATE Osignup SET fullname = ?, phonenumber = ? WHERE id = ?");
    if (!$stmt_update_osignup) throw new Exception("Prepare failed (update osignup): " . $conn->error);
    $stmt_update_osignup->bind_param("ssi", $ownername, $phonenumber, $owner_id);
    $stmt_update_osignup->execute();
    $stmt_update_osignup->close();

    // If all queries were successful, commit the transaction
    $conn->commit();
    $response = ['status' => 'success', 'message' => 'Profile updated successfully'];

} catch (Exception $e) {
    $conn->rollback(); // Roll back changes on error
    if ($e->getCode() == 1062) {
        $response = ['status' => 'error', 'message' => 'This phone number is already in use.'];
    } else {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
$conn->close();
?>