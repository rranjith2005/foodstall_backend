<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $current_month = date('m');
    $current_year = date('Y');
    $previous_month_date = date('Y-m-d', strtotime('first day of last month'));
    $previous_month = date('m', strtotime($previous_month_date));
    $previous_year = date('Y', strtotime($previous_month_date));

    $analysis_data = [
        'top_stalls' => [],
        'comparison' => []
    ];

    // --- Part 1: Top 5 Stalls (Current Month Revenue) ---
    $sql_top = "
        SELECT
            sd.stallname,
            COALESCE(SUM(o.total_amount), 0) as current_revenue
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
            current_revenue DESC
        LIMIT 5
    ";
    $stmt_top = $conn->prepare($sql_top);
    $stmt_top->bind_param("ii", $current_month, $current_year);
    $stmt_top->execute();
    $analysis_data['top_stalls'] = $stmt_top->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_top->close();

    // --- Part 2: Monthly Comparison (All Approved Stalls) ---
     $sql_comp = "
        SELECT
            sd.stall_id,
            sd.stallname,
            COALESCE(SUM(CASE WHEN MONTH(o.order_date) = ? AND YEAR(o.order_date) = ? THEN o.total_amount ELSE 0 END), 0) as current_month_revenue,
            COALESCE(SUM(CASE WHEN MONTH(o.order_date) = ? AND YEAR(o.order_date) = ? THEN o.total_amount ELSE 0 END), 0) as previous_month_revenue
        FROM
            stalldetails sd
        LEFT JOIN
            orders o ON sd.stall_id = o.stall_id AND o.order_status = 'Delivered'
                     AND ( (MONTH(o.order_date) = ? AND YEAR(o.order_date) = ?) OR (MONTH(o.order_date) = ? AND YEAR(o.order_date) = ?) )
        WHERE
            sd.approval = 1
        GROUP BY
            sd.stall_id, sd.stallname
        ORDER BY
            current_month_revenue DESC
    ";
    $stmt_comp = $conn->prepare($sql_comp);
    $stmt_comp->bind_param("iiiiiiii", $current_month, $current_year, $previous_month, $previous_year, $current_month, $current_year, $previous_month, $previous_year);
    $stmt_comp->execute();
    $analysis_data['comparison'] = $stmt_comp->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_comp->close();

    echo json_encode(['status' => 'success', 'data' => $analysis_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}

$conn->close();
?>