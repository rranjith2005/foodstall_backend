<?php
// google_auth.php - updated to return temporary password

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
include __DIR__ . '/config.php';

// CONFIG: Allowed client IDs (ensure this matches strings.xml AND google-services.json Web Client ID)
$ALLOWED_CLIENT_IDS = [
    '509454588471-pkbqseduu5j8bdh6ionk455pafhbii6t.apps.googleusercontent.com',
    '509454588471-u9op25cl4k78cr83rlp1vtqklouhkq50.apps.googleusercontent.com',
];

function log_debug($msg) {
    error_log("google_auth.php: " . $msg);
}

// Read ID token (robustly)
$rawPost = file_get_contents('php://input');
$id_token = $_POST['id_token'] ?? null;
$role_hint = $_POST['role_hint'] ?? 'user';

if (empty($id_token) && !empty($rawPost)) {
    $decoded = json_decode($rawPost, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (!empty($decoded['id_token'])) $id_token = $decoded['id_token'];
        if (!empty($decoded['role_hint'])) $role_hint = $decoded['role_hint'];
    } else {
        parse_str($rawPost, $parsedBody);
        if (empty($id_token) && !empty($parsedBody['id_token'])) $id_token = $parsedBody['id_token'];
        if (empty($role_hint) && !empty($parsedBody['role_hint'])) $role_hint = $parsedBody['role_hint'];
    }
}
if (is_string($id_token)) $id_token = trim($id_token);

if (empty($id_token)) {
    log_debug("No id_token provided in request.");
    echo json_encode(['status' => 'error', 'message' => 'ID token is required']);
    exit;
}
log_debug("Received ID token (len=" . strlen($id_token) . "). Role hint: " . ($role_hint ?? 'null'));

// Basic server readiness checks
if (!extension_loaded('curl')) { log_debug("cURL extension not enabled..."); echo json_encode(['status' => 'error', 'message' => 'Server misconfiguration: PHP cURL extension not enabled.']); exit; }
$cacertPath = null;
$iniCurlCA = ini_get('curl.cainfo') ?: ''; $iniOpenSSLCA = ini_get('openssl.cafile') ?: ''; $localCacert = __DIR__ . '/cacert.pem';
if (!empty($iniCurlCA) && file_exists($iniCurlCA)) $cacertPath = $iniCurlCA;
elseif (!empty($iniOpenSSLCA) && file_exists($iniOpenSSLCA)) $cacertPath = $iniOpenSSLCA;
elseif (file_exists($localCacert)) $cacertPath = $localCacert;
if ($cacertPath) log_debug("Using cacert: " . $cacertPath); else log_debug("No explicit cacert found.");

// Create Google client
$client = new Google_Client();
try {
    if (class_exists('GuzzleHttp\\Client')) {
        $guzzleOptions = ['timeout' => 10, 'connect_timeout' => 5, 'verify' => $cacertPath ?: true];
        $guzzle = new GuzzleHttp\Client($guzzleOptions);
        $client->setHttpClient($guzzle);
        log_debug("Guzzle client set.");
    } else { log_debug("Guzzle not present."); }
} catch (Throwable $t) { log_debug("Warning configuring Guzzle: " . $t->getMessage()); }

// Verify token signature + decode payload
$payload = null;
try {
    $payload = $client->verifyIdToken($id_token);
    if ($payload === false || !is_array($payload)) {
        log_debug("verifyIdToken returned false or non-array payload.");
        echo json_encode(['status' => 'error', 'message' => 'Invalid Google ID token.']);
        exit;
    }
    log_debug("verifyIdToken returned payload. sub=" . ($payload['sub'] ?? 'n/a') . ", aud=" . ($payload['aud'] ?? 'n/a'));
} catch (TypeError $te) { log_debug("TypeError during verifyIdToken: ".$te->getMessage()); echo json_encode(['status' => 'error', 'message' => 'Token verification failed (server type error).']); exit;
} catch (Exception $e) { $msg = $e->getMessage(); log_debug("Exception during verifyIdToken: ".$msg); echo json_encode(['status' => 'error', 'message' => (strpos($msg, 'segments') !== false ? 'Invalid token format.' : 'Token verification failed: '.$msg)]); exit; }

// Audience (client id) check
$aud = $payload['aud'] ?? null; $azp = $payload['azp'] ?? null; $accepted = false;
foreach ($ALLOWED_CLIENT_IDS as $allowed) { if ($aud === $allowed || $azp === $allowed) { $accepted = true; break; } }
if (!$accepted) {
    log_debug("Client ID mismatch. Token aud={$aud}, azp={$azp}. Allowed list: " . implode(',', $ALLOWED_CLIENT_IDS));
    echo json_encode(['status' => 'error', 'message' => 'Token verification failed: Invalid token format or Client ID mismatch.']);
    exit;
}

// DB logic
$google_id = $payload['sub'] ?? null;
$email = $payload['email'] ?? null;
$fullname = $payload['name'] ?? 'User';
log_debug("Payload accepted. email={$email}, sub={$google_id}, name={$fullname}");

$response = [];

try {
    $conn->begin_transaction();

    // Check usignup
    $stmt_user = $conn->prepare("SELECT id, fullname, student_id, email, is_admin FROM usignup WHERE email = ?");
    if (!$stmt_user) throw new Exception("Prepare failed (usignup check): " . $conn->error);
    $stmt_user->bind_param("s", $email);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user && $result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();
        $role = ($user['is_admin'] == 1) ? 'admin' : 'student';
        log_debug("User found in usignup. Role: {$role}");
        // TODO: Start session if needed
        $response = [
            'status' => 'success',
            'message' => 'Google login successful!',
            'role' => $role,
            'data' => [
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'student_id' => $user['student_id'],
                'email' => $user['email']
            ]
        ];
        $stmt_user->close();
    } else {
        if ($stmt_user) $stmt_user->close();

        // Check Osignup
        log_debug("Checking Osignup table for email: {$email}");
        $stmt_owner = $conn->prepare("SELECT o.id, o.phonenumber, sd.approval, sd.stall_id, sd.rejection_reason, sd.stallname FROM Osignup o LEFT JOIN stalldetails sd ON o.phonenumber = sd.phonenumber WHERE o.email = ?");
        if (!$stmt_owner) throw new Exception("Prepare failed (Osignup check): " . $conn->error);
        $stmt_owner->bind_param("s", $email);
        $stmt_owner->execute();
        $result_owner = $stmt_owner->get_result();

        if ($result_owner && $result_owner->num_rows === 1) {
            $owner = $result_owner->fetch_assoc();
            log_debug("User found in Osignup. Stall Approval: " . ($owner['approval'] ?? 'N/A'));
             // TODO: Start session if needed
            if ($owner['approval'] == 1 && $owner['stall_id']) {
                $response = [ /* owner approved response */ ];
            } else {
                $response = [ /* owner status check response */ ];
            }
            $stmt_owner->close();
        } else {
            if ($stmt_owner) $stmt_owner->close();

            // Register new user based on role_hint
            log_debug("User not found. Attempting registration with role hint: {$role_hint}");

            // --- MODIFICATION START: Generate plain text password ---
            $plain_text_password = bin2hex(random_bytes(4)); // Generate an 8-character hex password
            $hashed_password = password_hash($plain_text_password, PASSWORD_DEFAULT);
            // --- MODIFICATION END ---

            if ($role_hint === 'owner') {
                $phone_value = '';
                $insert_stmt = $conn->prepare("INSERT INTO Osignup (fullname, email, password, phonenumber) VALUES (?, ?, ?, ?)");
                if (!$insert_stmt) throw new Exception("Prepare failed (owner insert): " . $conn->error);
                $insert_stmt->bind_param("ssss", $fullname, $email, $hashed_password, $phone_value);
                $insert_stmt->execute();
                $owner_reg_id = $conn->insert_id;
                $insert_stmt->close();
                log_debug("New owner registered in Osignup. ID: {$owner_reg_id}");
                // TODO: Start session

                // --- MODIFICATION START: Update response for new owner ---
                $response = [
                    'status' => 'new_google_owner', // New status to indicate password step
                    'message' => 'Owner registered via Google! Please confirm password and add details.',
                    'role' => 'new_owner_google',
                    'data' => [
                        'fullname' => $fullname,
                        'email' => $email,
                        'temp_password' => $plain_text_password // Send plain text password
                    ]
                ];
                // --- MODIFICATION END ---

            } else { // default to user registration
                $temp_student_id = 'G' . time();
                $is_admin = 0;
                $phone_value = '';
                $insert_stmt = $conn->prepare("INSERT INTO usignup (fullname, student_id, email, password, is_admin, phonenumber) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$insert_stmt) throw new Exception("Prepare failed (user insert): " . $conn->error);
                $insert_stmt->bind_param("ssssis", $fullname, $temp_student_id, $email, $hashed_password, $is_admin, $phone_value);
                $insert_stmt->execute();
                $user_id = $conn->insert_id;
                $insert_stmt->close();
                log_debug("New user registered in usignup. ID: {$user_id}");
                // TODO: Start session

                // --- MODIFICATION START: Update response for new user ---
                $response = [
                    'status' => 'new_google_user', // New status to indicate password step
                    'message' => 'Google registration successful! Please confirm password.',
                    'role' => 'new_user_google', // Keep role distinct
                    'data' => [
                        'id' => $user_id, // Internal DB ID
                        'fullname' => $fullname,
                        'student_id' => $temp_student_id, // Generated student ID
                        'email' => $email,
                        'temp_password' => $plain_text_password // Send plain text password
                    ]
                ];
                // --- MODIFICATION END ---
            }
        }
    }

    if (empty($response)) {
         throw new Exception("Internal server error: No response generated.");
    }

    $conn->commit();
    log_debug("DB transaction committed.");

} catch (mysqli_sql_exception $e) { // Catch DB errors
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    log_debug("Database Exception: Code " . $e->getCode() . ", Msg: " . $e->getMessage());
    if ($e->getCode() == 1062) {
        $response = ['status' => 'error', 'message' => 'Account with this email already exists. Try logging in normally.'];
    } else {
        $response = ['status' => 'error', 'message' => 'Database Error: Please try again later.'];
    }
} catch (Exception $e) { // Catch general script errors
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    log_debug("General Exception: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
}

// Final send
log_debug("Final response: " . json_encode($response));
if (empty($response)) {
    $response = ['status' => 'error', 'message' => 'An unexpected server error occurred.'];
    log_debug("Error: Response was empty before final send. Sending generic error.");
}
echo json_encode($response);

// Close connection
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>