<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php'; // Includes your $conn variable

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
// This key comes from the Android app (e.g., 'user_privacy', 'owner_terms')
$policy_key_from_app = $_GET['policy_key'] ?? '';

if (empty($policy_key_from_app)) {
    echo json_encode(['status' => 'error', 'message' => 'Policy key is required.']);
    exit;
}

try {
    // [FIX] Corrected column names to match your table structure:
    // Query uses 'policy_type' in the WHERE clause.
    // Selects the 'content' column.
    $sql = "SELECT content FROM app_policies WHERE policy_type = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        // Log the actual prepare error for debugging if needed
        error_log("Database prepare error: " . $conn->error);
        throw new Exception("Database prepare error."); // Generic message to user
    }

    // Bind the key received from the app to the placeholder
    $stmt->bind_param("s", $policy_key_from_app);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $response = [
            'status' => 'success',
            // Return the content from the 'content' column
            'content' => $row['content'] ?? 'Content not found.'
        ];
    } else {
        // Log which key was not found
        error_log("Policy not found for key: " . $policy_key_from_app);
        $response = ['status' => 'error', 'message' => 'Policy not found for the given key.'];
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    // Log the detailed SQL error
    error_log("Database SQL Exception: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Database Error. Please try again later.']; // More user-friendly message
    http_response_code(500);
} catch (Exception $e) {
     // Log other server errors
    error_log("Server Exception: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Server Error. Please try again later.']; // More user-friendly message
    http_response_code(500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>