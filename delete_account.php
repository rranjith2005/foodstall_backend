<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php'; // Include your database connection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$identifier = $_POST['identifier'] ?? '';
$role = $_POST['role'] ?? ''; // Expect 'user', 'owner', or 'admin'

if (empty($identifier) || empty($role)) {
    echo json_encode(['status' => 'error', 'message' => 'Identifier and role are required']);
    exit;
}

try {
    $conn->begin_transaction(); // Start transaction for safety
    $deleted = false;

    if ($role === 'user') {
        // Delete student from usignup table
        $stmt = $conn->prepare("DELETE FROM usignup WHERE student_id = ? AND is_admin = 0");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $deleted = true;
            // TODO: Add deletes for related user data (orders, wallet_transactions, favorites) if no CASCADE
        }
        $stmt->close();

    } elseif ($role === 'owner') {
        // Owner identifier could be stall_id (if approved) or phonenumber (pre-approval/rejected)
        $phonenumber = null;
        $stall_id = null;

        if (strpos($identifier, 'S') === 0) {
            $stall_id = $identifier;
            // Need to get phonenumber to delete from Osignup
            $getPhoneStmt = $conn->prepare("SELECT phonenumber FROM stalldetails WHERE stall_id = ?");
            $getPhoneStmt->bind_param("s", $stall_id);
            $getPhoneStmt->execute();
            $result = $getPhoneStmt->get_result();
            if ($result->num_rows > 0) {
                $phonenumber = $result->fetch_assoc()['phonenumber'];
            }
            $getPhoneStmt->close();
        } else {
            // Assume identifier is phonenumber
            $phonenumber = $identifier;
            // Get stall_id if it exists to delete from stalldetails
             $getStallIdStmt = $conn->prepare("SELECT stall_id FROM stalldetails WHERE phonenumber = ?");
             $getStallIdStmt->bind_param("s", $phonenumber);
             $getStallIdStmt->execute();
             $result = $getStallIdStmt->get_result();
             if ($result->num_rows > 0) {
                 $stall_id = $result->fetch_assoc()['stall_id'];
             }
             $getStallIdStmt->close();
        }

        if ($phonenumber) {
            // Delete from stalldetails first (using phonenumber or stall_id if available)
            if ($stall_id) {
                 $stmt_details = $conn->prepare("DELETE FROM stalldetails WHERE stall_id = ?");
                 $stmt_details->bind_param("s", $stall_id);
            } else {
                 $stmt_details = $conn->prepare("DELETE FROM stalldetails WHERE phonenumber = ?");
                 $stmt_details->bind_param("s", $phonenumber);
            }
             $stmt_details->execute();
             // TODO: Add deletes for related owner data (menu, orders, rent) if no CASCADE
             $stmt_details->close();


            // Delete from Osignup using phonenumber
            $stmt_osignup = $conn->prepare("DELETE FROM Osignup WHERE phonenumber = ?");
            $stmt_osignup->bind_param("s", $phonenumber);
            $stmt_osignup->execute();
            if ($stmt_osignup->affected_rows > 0) {
                $deleted = true;
            }
            $stmt_osignup->close();
        }


    } elseif ($role === 'admin') {
        // Delete admin from usignup table (assuming admin_id is stored as student_id)
        $stmt = $conn->prepare("DELETE FROM usignup WHERE student_id = ? AND is_admin = 1");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $deleted = true;
        }
        $stmt->close();

    } else {
        throw new Exception("Invalid role specified");
    }

    if ($deleted) {
        $conn->commit(); // Commit transaction if delete was successful
        $response = ['status' => 'success', 'message' => 'Account deleted successfully'];
    } else {
        $conn->rollback(); // Rollback if no rows were affected (user not found)
        // [FIX] Removed the erroneous 'D' after 'status'
        $response = ['status' => 'error', 'message' => 'Account not found or could not be deleted'];
    }

} catch (mysqli_sql_exception $e) {
    if ($conn->in_transaction) $conn->rollback(); // Rollback on DB error
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
    http_response_code(500);
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback(); // Rollback on general error
    $response = ['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()];
    http_response_code(500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>