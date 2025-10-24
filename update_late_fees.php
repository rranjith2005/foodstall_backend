<?php
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Find all unpaid invoices where the due date (e.g., 8th of the month) has passed
    $due_date_day = 8;
    $current_day = date('j');
    
    // Only run if we are past the due date
    if ($current_day > $due_date_day) {
        $current_month = date('m');
        $current_year = date('Y');

        $sql = "
            SELECT invoice_id, generated_at 
            FROM rent_invoices 
            WHERE status = 'unpaid' 
              AND invoice_month = ? 
              AND invoice_year = ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $current_month, $current_year);
        $stmt->execute();
        $overdue_invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $update_stmt = $conn->prepare("UPDATE rent_invoices SET late_fee = ? WHERE invoice_id = ?");

        foreach ($overdue_invoices as $invoice) {
            $invoice_id = $invoice['invoice_id'];
            
            // Calculate days late since the 8th of the month
            $days_late = $current_day - $due_date_day;
            $late_fee = $days_late * 50;
            
            $update_stmt->bind_param("di", $late_fee, $invoice_id);
            $update_stmt->execute();
        }
        $update_stmt->close();
        
        echo "Late fees updated successfully for " . count($overdue_invoices) . " invoices.";
    } else {
        echo "Not past the due date yet. No action taken.";
    }

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>