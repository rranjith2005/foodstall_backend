<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

try {
    // Fetch all completed orders (status = 1)
    $stmt = $conn->prepare("
        SELECT o.stall_id, o.total_amount, o.order_items, s.stallname AS stallname
        FROM orders o
        JOIN stalldetails s ON o.stall_id = s.stall_id
        WHERE o.status = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $stall_data = [];

    while ($row = $result->fetch_assoc()) {
        $stall_id = $row['stall_id'];
        $amount = (float)$row['total_amount'];
        $items = json_decode($row['order_items'], true);

        if (!isset($stall_data[$stall_id])) {
            $stall_data[$stall_id] = [
                'stall_id' => $stall_id,
                'stallname' => $row['stallname'],
                'total_earnings' => 0,
                'product_sales' => []
            ];
        }

        $stall_data[$stall_id]['total_earnings'] += $amount;

        if (is_array($items)) {
            foreach ($items as $item) {
                if (!isset($item['name']) || !isset($item['quantity'])) continue;

                $name = $item['name'];
                $qty = (int)$item['quantity'];

                if (!isset($stall_data[$stall_id]['product_sales'][$name])) {
                    $stall_data[$stall_id]['product_sales'][$name] = 0;
                }

                $stall_data[$stall_id]['product_sales'][$name] += $qty;
            }
        }
    }

    $final = [];

    foreach ($stall_data as $stall_id => $data) {
        arsort($data['product_sales']);
        $top_product = array_key_first($data['product_sales']);
        $top_qty = $data['product_sales'][$top_product];

        $final[] = [
            'stall_id' => $data['stall_id'],
            'stallname' => $data['stallname'],
            'total_earnings' => $data['total_earnings'],
            'top_selling_product' => $top_product,
            'total_quantity_sold' => $top_qty
        ];
    }

    // Sort by total earnings descending
    usort($final, function ($a, $b) {
        return $b['total_earnings'] <=> $a['total_earnings'];
    });

    echo json_encode([
        'status' => 'success',
        'report' => $final
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch report: ' . $e->getMessage()
    ]);
}
