<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$student_id = $_POST['student_id'] ?? '';

if (empty($student_id)) {
    http_response_code(400);
    die(json_encode([]));
}

try {
    // --- UPDATED QUERY TO INCLUDE refund_timestamp ---
    $sql = "
        SELECT 
            o.*,
            sd.stallname,
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
        JOIN stalldetails sd ON o.stall_id = sd.stall_id
        WHERE o.student_id = ?
        ORDER BY o.order_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
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