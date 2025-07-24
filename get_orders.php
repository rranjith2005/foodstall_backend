<?php
header('Content-Type: application/json');
include 'config.php';

try {
    // Get student_id or stall_id from GET parameters
    $student_id = $_GET['student_id'] ?? '';
    $stall_id = $_GET['stall_id'] ?? '';

    if (empty($student_id) && empty($stall_id)) {
        echo json_encode([
            "status" => "error",
            "message" => "student_id or stall_id is required"
        ]);
        exit;
    }

    if (!empty($student_id)) {
        $stmt = $conn->prepare("SELECT * FROM Orders WHERE student_id = ? ORDER BY order_date DESC, order_time DESC");
        $stmt->bind_param("s", $student_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Orders WHERE stall_id = ? ORDER BY order_date DESC, order_time DESC");
        $stmt->bind_param("s", $stall_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['status_text'] = ($row['status'] == 1) ? "approved" : (($row['status'] == 0) ? "rejected" : "pending");
            $orders[] = $row;
        }

        echo json_encode([
            "status" => "success",
            "orders" => $orders
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "message" => "No orders found"
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Fetch failed: " . $e->getMessage()
    ]);
}
?>
