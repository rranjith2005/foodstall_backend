<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

try {
    $stall_id = $_GET['stall_id'] ?? $_POST['stall_id'] ?? null;
    $menu_date = $_GET['menu_date'] ?? $_POST['menu_date'] ?? date('Y-m-d');

    if (!$stall_id) {
        throw new Exception("Missing stall_id");
    }

    $stmt = $conn->prepare("SELECT * FROM MenuDetails WHERE stall_id = ? AND menu_date = ?");
    $stmt->bind_param("ss", $stall_id, $menu_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            "status" => "success",
            "menu" => [
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
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No menu found for this stall on the given date"
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
