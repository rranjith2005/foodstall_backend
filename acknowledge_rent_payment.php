<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php'; // Include your database connection

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$response = [];
$invoice_id = $_POST['invoice_id'] ?? null;

if (empty($invoice_id) || !is_numeric($invoice_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Valid Invoice ID is required.']);
    exit;
}

try {
    $conn->begin_transaction();

    // Update the is_acknowledged flag for the specific invoice
    $sql = "UPDATE rent_invoices SET is_acknowledged = 1 WHERE invoice_id = ? AND status = 'paid'"; // Only acknowledge if paid
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }

    $invoice_id_int = (int)$invoice_id;
    $stmt->bind_param("i", $invoice_id_int);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        $response = ['status' => 'success', 'message' => 'Rent payment acknowledged.'];
    } else {
        $conn->rollback();
        // Check if the invoice exists and is paid, maybe it was already acknowledged?
         $checkStmt = $conn->prepare("SELECT status, is_acknowledged FROM rent_invoices WHERE invoice_id = ?");
         $checkStmt->bind_param("i", $invoice_id_int);
         $checkStmt->execute();
         $result = $checkStmt->get_result();
         if($result->num_rows > 0) {
              $row = $result->fetch_assoc();
              if($row['status'] !== 'paid') {
                   $response = ['status' => 'error', 'message' => 'Cannot acknowledge unpaid invoice.'];
              } elseif ($row['is_acknowledged'] == 1) {
                   $response = ['status' => 'success', 'message' => 'Payment already acknowledged.']; // Treat as success for UI
              } else {
                   $response = ['status' => 'error', 'message' => 'Failed to acknowledge payment. Unknown reason.'];
              }
         } else {
              $response = ['status' => 'error', 'message' => 'Invoice not found.'];
         }
         $checkStmt->close();
    }
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    error_log("Acknowledge Rent DB Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Database Error.'];
    http_response_code(500);
} catch (Exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    error_log("Acknowledge Rent Server Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Server Error.'];
    http_response_code(500);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo json_encode($response);
?>