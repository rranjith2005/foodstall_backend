<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

try {
    $menu_date = $_GET['menu_date'] ?? $_POST['menu_date'] ?? date('Y-m-d');

    // Join with stalldetails and check approval = 1
    $stmt = $conn->prepare("
        SELECT m.* 
        FROM MenuDetails m
        INNER JOIN stalldetails s ON m.stall_id = s.stall_id
        WHERE m.menu_date = ? AND s.approval = 1
    ");
    $stmt->bind_param("s", $menu_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $menus = [];

    while ($row = $result->fetch_assoc()) {
        $menus[] = [
            "stall_id" => $row['stall_id'],
            "working" => $row['working'],
            "today_special" => [
                "name" => $row['todays_special_name'],
                "price" => $row['todays_special_price'],
                "quantity" => $row['todays_special_quantity']
            ],
            "regular_menu" => isset($row['regular_menu']) && $row['regular_menu'] ? json_decode($row['regular_menu'], true) : [],
            "combo_details" => isset($row['combo_details']) && $row['combo_details'] ? json_decode($row['combo_details'], true) : [],
            "opening_hours" => $row['opening_hours'],
            "closing_hours" => $row['closing_hours'],
            "break_time" => [
                "start" => $row['break_start'],
                "end" => $row['break_end']
            ],
            "menu_date" => $row['menu_date']
        ];
    }

    if (empty($menus)) {
        echo json_encode([
            "status" => "error",
            "message" => "No menus found on the given date for approved stalls"
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "menu_date" => $menu_date,
            "menus" => $menus
        ]);
    }

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "MySQLi Exception: " . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
