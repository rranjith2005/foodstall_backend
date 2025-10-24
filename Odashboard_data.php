<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $stall_id = $_REQUEST['stall_id'] ?? '';
    if(empty($stall_id)) { throw new Exception('Stall ID is required.'); }

    $response = [];

    // --- SUMMARY CARD DATA (Unchanged) ---
    $stmt_orders = $conn->prepare("SELECT COUNT(order_id) as count FROM orders WHERE stall_id = ? AND order_status IN ('Delivered', 'Rejected') AND DATE(order_date) = CURDATE()");
    $stmt_orders->bind_param("s", $stall_id);
    $stmt_orders->execute();
    $response['orders_today'] = $stmt_orders->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_orders->close();
    
    $stmt_rev = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE stall_id = ? AND DATE(order_date) = CURDATE() AND order_status = 'Delivered'");
    $stmt_rev->bind_param("s", $stall_id);
    $stmt_rev->execute();
    $response['revenue_today'] = $stmt_rev->get_result()->fetch_assoc()['total'] ?? 0.00;
    $stmt_rev->close();

    $stmt_pending = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE stall_id = ? AND order_status = 'Pending' AND DATE(order_date) = CURDATE()");
    $stmt_pending->bind_param("s", $stall_id);
    $stmt_pending->execute();
    $response['pending_orders'] = $stmt_pending->get_result()->fetch_assoc()['count'] ?? 0;
    $stmt_pending->close();

    $stmt_top = $conn->prepare("SELECT oi.item_name FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE o.stall_id = ? GROUP BY oi.item_name ORDER BY SUM(oi.quantity) DESC LIMIT 1");
    $stmt_top->bind_param("s", $stall_id);
    $stmt_top->execute();
    $response['top_selling'] = $stmt_top->get_result()->fetch_assoc()['item_name'] ?? 'N/A';
    $stmt_top->close();

    // --- Revenue Trend Data (Unchanged) ---
    $stmt_trend = $conn->prepare("
        SELECT DATE(order_date) as date, SUM(total_amount) as revenue 
        FROM orders 
        WHERE stall_id = ? AND order_status = 'Delivered' AND order_date >= CURDATE() - INTERVAL 30 DAY 
        GROUP BY DATE(order_date) 
        ORDER BY date ASC
    ");
    $stmt_trend->bind_param("s", $stall_id);
    $stmt_trend->execute();
    $response['revenue_trend'] = $stmt_trend->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_trend->close();

    // --- Peak Hours Data (Unchanged) ---
    $stmt_peak = $conn->prepare("
        SELECT HOUR(order_date) as hour, COUNT(order_id) as order_count 
        FROM orders 
        WHERE stall_id = ? AND order_status = 'Delivered'
        GROUP BY HOUR(order_date) 
        ORDER BY hour ASC
    ");
    $stmt_peak->bind_param("s", $stall_id);
    $stmt_peak->execute();
    $response['peak_hours'] = $stmt_peak->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_peak->close();
    
    // ***** NEW: RENT DETAILS CHECK *****
    $current_month = date('n');
    $current_year = date('Y');
    
    $stmt_rent = $conn->prepare(
        "SELECT invoice_id, total_revenue, rent_amount, late_fee, invoice_month, invoice_year 
         FROM rent_invoices 
         WHERE stall_id = ? AND invoice_month = ? AND invoice_year = ? AND status = 'unpaid'"
    );
    $stmt_rent->bind_param("sii", $stall_id, $current_month, $current_year);
    $stmt_rent->execute();
    $rent_details = $stmt_rent->get_result()->fetch_assoc();
    $stmt_rent->close();

    // If rent details are found, add them to the response, otherwise add null
    $response['rent_details'] = $rent_details ? $rent_details : null;
    // **********************************

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>