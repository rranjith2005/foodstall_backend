<?php
include 'config.php';
require 'vendor/autoload.php'; // Make sure this path is correct

// Get parameters
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
$stmt->close();
$conn->close();

if (!$invoice || $invoice['status'] !== 'paid') {
    header("HTTP/1.1 404 Not Found");
    die("Paid invoice not found.");
}

// Create PDF
$pdf = new FPDF('P', 'mm', 'A5');
$pdf->AddPage();

// Header
$pdf->SetFont('Arial', 'B', 20);
$pdf->Cell(0, 12, 'Rent Payment Receipt', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 8, 'Stall Spot', 0, 1, 'C');
$pdf->Ln(10);

// Receipt Details
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 8, 'Invoice ID:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, '#' . $invoice['invoice_id'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 8, 'Stall Name:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $invoice['stallname'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 8, 'Owner Name:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, $invoice['ownername'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 8, 'Payment Date:', 0, 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, date('d M Y, h:i A', strtotime($invoice['paid_at'])), 0, 1);

$pdf->Ln(10);

// Payment Summary Table
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(90, 10, 'Description', 1, 0, 'L', true);
$pdf->Cell(40, 10, 'Amount', 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 12);
$month_name = DateTime::createFromFormat('!m', $invoice['invoice_month'])->format('F');
$pdf->Cell(90, 10, 'Rent for ' . $month_name . ' ' . $invoice['invoice_year'], 1, 0, 'L');
$pdf->Cell(40, 10, 'Rs. ' . number_format($invoice['rent_amount'], 2), 1, 1, 'R');

if ($invoice['late_fee'] > 0) {
    $pdf->Cell(90, 10, 'Late Fee', 1, 0, 'L');
    $pdf->Cell(40, 10, 'Rs. ' . number_format($invoice['late_fee'], 2), 1, 1, 'R');
}

// Total
$total_paid = $invoice['rent_amount'] + $invoice['late_fee'];
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(90, 12, 'Total Paid', 1, 0, 'R');
$pdf->Cell(40, 12, 'Rs. ' . number_format($total_paid, 2), 1, 1, 'R');
$pdf->Ln(15);

// "Paid" Stamp
$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(34, 177, 76);
$pdf->Cell(0, 10, 'PAID', 0, 1, 'C');

// Output PDF
$filename = "RentReceipt_{$invoice['stall_id']}_{$invoice_id}.pdf";
$pdf->Output('D', $filename); // 'D' forces a download dialog
exit;
?>