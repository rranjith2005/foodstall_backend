<?php
header('Content-Type: application/json');
include 'config.php';

// Get status and optional stall_id via GET parameters
$status = $_GET['status'] ?? '';
$stall_id = $_GET['stall_id'] ?? '';

if ($status === '') {
    echo json_encode(["status" => "error", "message" => "Order status is required"]);
    exit;
}

try {
    if (!empty($stall_id)) {
        // For owner to view orders of their stall
        $stmt = $conn->prepare("SELECT * FROM Orders WHERE stall_id = ? AND status = ?");
        $stmt->bind_param("si", $stall_id, $status);
    } else {
        // For admin to view all orders by status
        $stmt = $conn->prepare("SELECT * FROM Orders WHERE status = ?");
        $stmt->bind_param("i", $status);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        // Map status to readable text
        if ($row['status'] == 1) {
            $row['status_text'] = "Approved";
        } elseif ($row['status'] == 0) {
            $row['status_text'] = "Rejected";
        } elseif ($row['status'] == -1) {
            $row['status_text'] = "User Cancelled";
        } else {
            $row['status_text'] = "Pending";
        }

        $orders[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "orders" => $orders
    ]);

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed: " . $e->getMessage()
    ]);
}
?>
