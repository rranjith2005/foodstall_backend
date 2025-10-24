<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Use $_REQUEST to allow testing with GET in browser
$stall_id = $_REQUEST['stall_id'] ?? '';
$date = $_REQUEST['date'] ?? '';

if (empty($stall_id)) {
    http_response_code(400);
    die(json_encode(["error" => "Stall ID is required."]));
}
if (empty($date)) {
    http_response_code(400);
    die(json_encode(["error" => "Date is required."]));
}

try {
    // MODIFIED: Added 'refund_timestamp' to SELECT and filtered by status
    $sql = "
        SELECT 
            o.order_id,
            o.stall_id,
            o.student_id,
            o.total_amount,
            o.subtotal,
            o.parcel_fee,
            o.order_status,
            o.order_date,
            o.payment_method,
            o.parcel_type,
            o.payment_id,
            o.pickup_time,
            o.display_order_id,
            o.refund_timestamp, -- Added this line
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'name', oi.item_name, 
                        'quantity', oi.quantity, 
                        'price', oi.price,
                        'parcel_status', oi.parcel_status
                    )
                ) 
                FROM order_items oi
                WHERE oi.order_id = o.order_id
            ) as items_json
        FROM orders o
        WHERE 
            o.stall_id = ? 
            AND DATE(o.order_date) = ?
            AND o.order_status IN ('Delivered', 'Rejected') -- Added this line
        ORDER BY 
            o.order_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $stall_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($orders);

} catch (Exception $e) { 
    http_response_code(500);
    echo json_encode(["error" => "An internal server error occurred.", "message" => $e->getMessage()]); 
}

$conn->close();
?>