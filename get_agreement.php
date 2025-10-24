<?php
header('Content-Type: application/json');
include 'config.php';

try {
    // We fetch the agreement specifically for owners.
    $stmt = $conn->prepare("SELECT content FROM app_policies WHERE policy_type = 'owner_agreement' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $policy = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'content' => $policy['content']]);
    } else {
        throw new Exception("Agreement policy not found in the database.");
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>