<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Logging setup
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log'); // Ensure this path is writable by the server
// --- End logging setup ---
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$identifier = $_POST['identifier'] ?? '';
$password = $_POST['password'] ?? '';

error_log("--- New login.php request ---"); // Log new request
error_log("Identifier received: " . $identifier); // Log identifier

if (empty($identifier) || empty($password)) {
    error_log("Login failed: Missing identifier or password.");
    echo json_encode(['status' => 'error', 'message' => 'ID and password are required']);
    exit;
}

try {
    $user_found = false;

    // --- Scenario 1: Check for an approved Owner logging in with their Stall ID ---
    if (strpos($identifier, 'S') === 0) {
        error_log("Scenario 1: Detected Stall ID login for ID: " . $identifier); // Log entering Scenario 1
        // [FIX from previous response] Changed 'IS NOT NULL' to 'IS NULL'
        $stmt_owner = $conn->prepare(
            "SELECT sd.stallname, sd.agreement_accepted, o.password, o.phonenumber
             FROM stalldetails sd
             JOIN Osignup o ON sd.phonenumber = o.phonenumber
             WHERE sd.stall_id = ? AND sd.approval = 1"
        );
        $stmt_owner->bind_param("s", $identifier);
        $stmt_owner->execute();
        $result_owner = $stmt_owner->get_result();

        error_log("Scenario 1: Query executed. Number of rows found: " . $result_owner->num_rows);

        if ($result_owner->num_rows === 1) {
            $owner_details = $result_owner->fetch_assoc();
            $hash_preview = substr($owner_details['password'], 0, 10);
            error_log("Scenario 1: Found owner. Hash preview: " . $hash_preview . "... Attempting password_verify.");

            if (password_verify($password, $owner_details['password'])) {
                error_log("Scenario 1: password_verify successful.");
                $user_found = true;

                if ($owner_details['agreement_accepted'] === null) {
                    error_log("Scenario 1: Agreement IS NULL. Setting role to owner_agreement_pending.");
                    $response = [
                        'status' => 'success',
                        'message' => 'Please accept the terms to continue.',
                        'role' => 'owner_agreement_pending',
                        'data' => [
                            'phonenumber' => $owner_details['phonenumber'],
                            'stall_id' => $identifier,
                            'stall_name' => $owner_details['stallname']
                        ]
                    ];
                } else {
                     error_log("Scenario 1: Agreement IS NOT NULL (Value: '" . $owner_details['agreement_accepted'] . "'). Setting role to owner_approved.");
                    $response = [
                        'status' => 'success',
                        'message' => 'Owner login successful!',
                        'role' => 'owner_approved',
                        'data' => [ 'stall_id' => $identifier, 'stall_name' => $owner_details['stallname'] ]
                    ];
                }
            } else {
                 error_log("Scenario 1: password_verify FAILED.");
            }
        } else {
             error_log("Scenario 1: Stall ID not found in database or approval != 1.");
        }
        $stmt_owner->close();
    }

    // --- Scenario 2: If not a Stall ID, check if it's a User/Student ---
    if (!$user_found) {
        // [THIS IS THE FIX] The 'data' array structure was corrected below
        $stmt_user = $conn->prepare("SELECT * FROM usignup WHERE student_id = ? AND is_admin = 0");
        $stmt_user->bind_param("s", $identifier);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();

        if ($result_user->num_rows === 1) {
            $user = $result_user->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $user_found = true;
                // [FIX START] Corrected the 'data' part to be an object with user details
                $response = [
                    'status' => 'success',
                    'message' => 'User login successful!',
                    'role' => 'student',
                    'data' => [ // Changed from [] to associative array
                        'id' => $user['id'],
                        'fullname' => $user['fullname'],
                        'student_id' => $user['student_id'],
                        'email' => $user['email']
                        // Add any other student fields you need here
                    ]
                ];
                // [FIX END]
            }
        }
        $stmt_user->close();
    }

    // --- Scenario 3: If not found yet, check if it's an Owner by Phone Number ---
    if (!$user_found) {
        error_log("Scenario 3: Checking Phone Number login for identifier: " . $identifier);
        // Unchanged query
        $stmt_owner_phone = $conn->prepare(
            "SELECT o.*, sd.approval, sd.stall_id, sd.rejection_reason, sd.stallname, sd.agreement_accepted
             FROM Osignup o
             LEFT JOIN stalldetails sd ON o.phonenumber = sd.phonenumber
             WHERE o.phonenumber = ?"
        );
        $stmt_owner_phone->bind_param("s", $identifier);
        $stmt_owner_phone->execute();
        $result_owner_phone = $stmt_owner_phone->get_result();

        if ($result_owner_phone->num_rows === 1) {
            $owner = $result_owner_phone->fetch_assoc();
             error_log("Scenario 3: Found owner by phone. Attempting password_verify.");
            if (password_verify($password, $owner['password'])) {
                 error_log("Scenario 3: password_verify successful.");
                $user_found = true;

                if ($owner['approval'] == 1) {
                    if ($owner['agreement_accepted'] === null) {
                        error_log("Scenario 3: Approved, agreement NULL. Role owner_approval_pending_view.");
                        $response = [
                            'status' => 'success',
                            'message' => 'Your stall has been approved!',
                            'role' => 'owner_approval_pending_view',
                            'data' => [ 'stall_id' => $owner['stall_id'] ]
                        ];
                    } else {
                         error_log("Scenario 3: Approved, agreement NOT NULL. Role owner_approved.");
                        // Unchanged owner_approved response structure
                         $response = [
                            'status' => 'success',
                            'message' => 'Owner login successful!',
                            'role' => 'owner_approved',
                            'data' => [ 'stall_id' => $owner['stall_id'], 'stall_name' => $owner['stallname'] ]
                         ];
                    }
                } else {
                     error_log("Scenario 3: Not approved (status: " . ($owner['approval'] ?? 'null') . "). Role owner_status_check.");
                     // Unchanged owner_status_check response structure
                    $response = [
                        'status' => 'success',
                        'message' => 'Owner status check successful!',
                        'role' => 'owner_status_check',
                        'data' => [
                            'stall_status' => $owner['approval'] ?? 0,
                            'stall_id' => $owner['stall_id'] ?? null,
                            'rejection_reason' => $owner['rejection_reason'] ?? null,
                            'fullname' => $owner['fullname'],
                            'email' => $owner['email'],
                            'phonenumber' => $owner['phonenumber']
                        ]
                    ];
                }
            } else {
                error_log("Scenario 3: password_verify FAILED.");
            }
        } else {
            error_log("Scenario 3: Owner not found for phone number: " . $identifier);
        }
        $stmt_owner_phone->close();
    }

    if (!$user_found) {
        error_log("Login failed: User not found in any scenario or password mismatch."); // Log final failure
        $response = ['status' => 'error', 'message' => 'Invalid credentials or user not found'];
    }

} catch (mysqli_sql_exception $e) {
    error_log("Database Exception: " . $e->getMessage()); // Log DB errors
    $response = ['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()];
} catch (Exception $e) { // Catch any other general errors
    error_log("General Exception: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()];
}


error_log("Final response being sent: " . json_encode($response)); // Log the final response
echo json_encode($response);
$conn->close();
?>