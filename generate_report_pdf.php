<?php
include 'config.php';
require 'vendor/autoload.php';

// Get parameters
$stall_id = $_GET['stall_id'] ?? '';
$month = $_GET['month'] ?? '';
$year = $_GET['year'] ?? '';

if (empty($stall_id) || empty($month) || empty($year)) {
    header("HTTP/1.1 400 Bad Request");
    die("Error: Missing required parameters.");
}

// Fetch stall name
$stmt_stall = $conn->prepare("SELECT stallname FROM stalldetails WHERE stall_id = ?");
$stmt_stall->bind_param("s", $stall_id);
$stmt_stall->execute();
$stall_name = $stmt_stall->get_result()->fetch_assoc()['stallname'] ?? 'Unknown Stall';
$stmt_stall->close();

// Fetch orders
$stmt_orders = $conn->prepare(
    "SELECT display_order_id, order_date, order_status, total_amount 
     FROM orders 
     WHERE stall_id = ? AND MONTH(order_date) = ? AND YEAR(order_date) = ? AND order_status IN ('Delivered', 'Rejected')
     ORDER BY order_date ASC"
);
$stmt_orders->bind_param("sii", $stall_id, $month, $year);
$stmt_orders->execute();
$orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_orders->close();
$conn->close();

// Calculate totals
$total_revenue = 0;
$delivered_count = 0;
$rejected_count = 0;
foreach ($orders as $order) {
    if ($order['order_status'] === 'Delivered') {
        $total_revenue += $order['total_amount'];
        $delivered_count++;
    } else {
        $rejected_count++;
    }
}

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();

// Header
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Monthly Sales Statement', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $stall_name . ' (' . $stall_id . ')', 0, 1, 'C');
$month_name = DateTime::createFromFormat('!m', $month)->format('F');
$pdf->Cell(0, 10, 'Report for: ' . $month_name . ' ' . $year, 0, 1, 'C');
$pdf->Ln(10);

// Summary
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 10, 'Total Revenue (from Delivered Orders):', 1, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(95, 10, 'Rs. ' . number_format($total_revenue, 2), 1, 1, 'R');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 10, 'Delivered Orders:', 1, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(95, 10, $delivered_count, 1, 1, 'R');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 10, 'Rejected Orders:', 1, 0, 'L');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(95, 10, $rejected_count, 1, 1, 'R');
$pdf->Ln(10);

// Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(40, 10, 'Order ID', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Date & Time', 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Status', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Amount', 1, 1, 'C', true);

// Table Rows
$pdf->SetFont('Arial', '', 9);
foreach ($orders as $order) {
    $pdf->Cell(40, 8, $order['display_order_id'], 1, 0, 'L');
    $pdf->Cell(60, 8, date('d-m-Y H:i A', strtotime($order['order_date'])), 1, 0, 'L');
    
    if ($order['order_status'] === 'Delivered') {
        $pdf->SetTextColor(34, 139, 34); // ForestGreen
    } else {
        $pdf->SetTextColor(220, 20, 60); // Crimson
    }
    $pdf->Cell(40, 8, $order['order_status'], 1, 0, 'C');
    $pdf->SetTextColor(0, 0, 0); // Reset color
    
    $pdf->Cell(50, 8, 'Rs. ' . number_format($order['total_amount'], 2), 1, 1, 'R');
}

// Output PDF
$filename = "SalesReport_{$stall_id}_{$month}_{$year}.pdf";
$pdf->Output('D', $filename); // 'D' forces a download dialog
exit;
?>