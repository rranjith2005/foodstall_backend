<?php
include 'config.php';
require 'vendor/autoload.php';

$invoice_id = $_GET['invoice_id'] ?? 0;
if (empty($invoice_id)) {
    header("HTTP/1.1 400 Bad Request");
    die("Error: Invoice ID is required.");
}

// Fetch invoice and stall details
$stmt = $conn->prepare(
    "SELECT ri.*, sd.stallname, sd.ownername 
     FROM rent_invoices ri 
     JOIN stalldetails sd ON ri.stall_id = sd.stall_id 
     WHERE ri.invoice_id = ?"
);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header("HTTP/1.1 404 Not Found");
    die("Invoice not found.");
}

$stall_id = $invoice['stall_id'];
$month = $invoice['invoice_month'];
$year = $invoice['invoice_year'];
$month_name = DateTime::createFromFormat('!m', $month)->format('F');

// Fetch all relevant orders for that month
$stmt_orders = $conn->prepare(
    "SELECT display_order_id, order_date, order_status, total_amount 
     FROM orders 
     WHERE stall_id = ? AND MONTH(order_date) = ? AND YEAR(order_date) = ? AND order_status IN ('Delivered', 'Rejected')
     ORDER BY order_date ASC"
);
$stmt_orders->bind_param("sii", $stall_id, $month, $year);
$stmt_orders->execute();
$orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// --- PDF Generation ---
$pdf = new FPDF();
$pdf->AddPage();
// ... (Similar PDF generation logic as generate_rent_receipt_pdf.php) ...
// For brevity, you can reuse the logic to create a detailed report with a PAID/UNPAID watermark.

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Owner Revenue Statement', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $invoice['stallname'] . ' (' . $stall_id . ')', 0, 1, 'C');
$pdf->Cell(0, 10, 'Statement for: ' . $month_name . ' ' . $year, 0, 1, 'C');
$pdf->Ln(5);

// Watermark
if (strcasecmp($invoice['status'], 'paid') != 0) {
    $pdf->SetFont('Arial', 'B', 50);
    $pdf->SetTextColor(255, 192, 192);
    $pdf->RotatedText(35, 190, 'UNPAID', 45);
    $pdf->SetTextColor(0, 0, 0);
}

// Summary
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 10, 'Total Revenue (from Delivered Orders):', 1, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(95, 10, 'Rs. ' . number_format($invoice['total_revenue'], 2), 1, 1, 'R');
// ... add more summary details like rent, late fee, etc.

$pdf->Ln(5);
// Table of all orders... (similar to generate_report_pdf.php)

$filename = "OwnerStatement_{$stall_id}_{$month}_{$year}.pdf";
$pdf->Output('D', $filename);
exit;

// Helper function for watermark
class PDF extends FPDF {
    var $angle=0;
    function RotatedText($x, $y, $txt, $angle) {
        $this->angle=$angle;
        $this->x=$x;
        $this->y=$y;
        $this->SetFont('Arial','B',50);
        $this->SetTextColor(255,192,192);
        $this->Text($x,$y,$txt);
    }
}
?>