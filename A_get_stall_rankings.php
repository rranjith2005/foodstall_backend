<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $current_month = date('m');
    $current_year = date('Y');

    $ranked_stalls = [];
    $rank = 1;

    // --- Query for ranking based on revenue (Unchanged) ---
    $sql_rank = "
        SELECT
            sd.stall_id,
            sd.stallname,
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM
            stalldetails sd
        LEFT JOIN
            orders o ON sd.stall_id = o.stall_id
                    AND o.order_status = 'Delivered'
                    AND MONTH(o.order_date) = ?
                    AND YEAR(o.order_date) = ?
        WHERE
            sd.approval = 1
        GROUP BY
            sd.stall_id, sd.stallname
        ORDER BY
            total_revenue DESC
    ";

    $stmt_rank = $conn->prepare($sql_rank);
    $stmt_rank->bind_param("ii", $current_month, $current_year);
    $stmt_rank->execute();
    $result_rank = $stmt_rank->get_result();

    // --- Query for best selling item (Unchanged) ---
    $stmt_item = $conn->prepare("
        SELECT oi.item_name, SUM(oi.quantity) as total_quantity
        FROM order_items oi JOIN orders o ON oi.order_id = o.order_id
        WHERE o.stall_id = ? AND o.order_status = 'Delivered'
          AND MONTH(o.order_date) = ? AND YEAR(o.order_date) = ?
        GROUP BY oi.item_name ORDER BY total_quantity DESC LIMIT 1
    ");

    // --- Query for latest rent invoice [MODIFIED] ---
    // Added 'is_acknowledged' to the SELECT list
    $stmt_invoice = $conn->prepare("
        SELECT invoice_id, rent_amount, late_fee, status as rent_status, invoice_month, invoice_year, is_acknowledged
        FROM rent_invoices
        WHERE stall_id = ?
        ORDER BY invoice_year DESC, invoice_month DESC
        LIMIT 1
    ");

    while ($stall = $result_rank->fetch_assoc()) {
        $stall_id = $stall['stall_id'];

        // --- Fetch best selling item (Unchanged) ---
        $stmt_item->bind_param("sii", $stall_id, $current_month, $current_year);
        $stmt_item->execute();
        $top_item_result = $stmt_item->get_result()->fetch_assoc();
        $best_selling_item_text = $top_item_result ? ($top_item_result['item_name'] . ' - ' . $top_item_result['total_quantity'] . ' sold') : 'N/A';

        // --- Fetch latest invoice [MODIFIED LOGIC] ---
        $stmt_invoice->bind_param("s", $stall_id);
        $stmt_invoice->execute();
        $invoice_result = $stmt_invoice->get_result()->fetch_assoc();

        $invoice_id = null;
        $rent_amount = null;
        $late_fee = null;
        $rent_status = null;
        $is_acknowledged = 0; // Default to not acknowledged

        if ($invoice_result) {
            $is_acknowledged = (int)$invoice_result['is_acknowledged'];
            $current_rent_status = $invoice_result['rent_status'];

            // [NEW LOGIC] Only include rent details if it's NOT paid AND acknowledged
            if (!($current_rent_status === 'paid' && $is_acknowledged === 1)) {
                $invoice_id = (int)$invoice_result['invoice_id'];
                $rent_amount = (double)$invoice_result['rent_amount'];
                $late_fee = (double)$invoice_result['late_fee'];
                $rent_status = $current_rent_status; // Keep the actual status
            }
            // If it IS paid and acknowledged, the variables remain null
        }
        // --- End Modified Invoice Logic ---

        // Add stall data to the final array
        $ranked_stalls[] = [
            'rank' => '#' . $rank++,
            'stallName' => $stall['stallname'],
            'stallId' => $stall['stall_id'],
            'totalRevenue' => (double)$stall['total_revenue'],
            'bestSellingItem' => $best_selling_item_text,
            // These will be null if the rent was paid and acknowledged
            'invoiceId' => $invoice_id,
            'rentAmount' => $rent_amount,
            'lateFee' => $late_fee,
            'rentStatus' => $rent_status
            // Note: We don't need to send is_acknowledged to the app
        ];
    }

    $stmt_rank->close();
    $stmt_item->close();
    $stmt_invoice->close();

    echo json_encode($ranked_stalls);

} catch (Exception $e) {
    http_response_code(500);
    // Log the error for server-side debugging
    error_log("Error in A_get_stall_rankings.php: " . $e->getMessage());
    // Send a generic error to the client
    echo json_encode(["error" => "An internal server error occurred."]);
}

$conn->close();
?>