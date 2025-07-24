<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

try {
    $menu_date = $_GET['menu_date'] ?? $_POST['menu_date'] ?? date('Y-m-d');

    // Fetch all approved stalls
    $stallQuery = "SELECT * FROM stalldetails WHERE approval = 1";
    $stallResult = $conn->query($stallQuery);

    $stalls = [];

    while ($stall = $stallResult->fetch_assoc()) {
        $stall_id = $stall['stall_id'];

        // Fetch menu for each stall on given date
        $menuStmt = $conn->prepare("SELECT * FROM MenuDetails WHERE stall_id = ? AND menu_date = ?");
        $menuStmt->bind_param("ss", $stall_id, $menu_date);
        $menuStmt->execute();
        $menuResult = $menuStmt->get_result();

        $menu = null;
        if ($menuRow = $menuResult->fetch_assoc()) {
            $menu = [
                "working" => $menuRow['working'],
                "today_special" => [
                    "name" => $menuRow['todays_special_name'],
                    "price" => $menuRow['todays_special_price'],
                    "quantity" => $menuRow['todays_special_quantity']
                ],
                "regular_menu" => isset($menuRow['regular_menu']) && $menuRow['regular_menu'] ? json_decode($menuRow['regular_menu'], true) : [],
                "combo_details" => isset($menuRow['combo_details']) && $menuRow['combo_details'] ? json_decode($menuRow['combo_details'], true) : [],
                "opening_hours" => $menuRow['opening_hours'],
                "closing_hours" => $menuRow['closing_hours'],
                "break_time" => [
                    "start" => $menuRow['break_start'],
                    "end" => $menuRow['break_end']
                ],
                "menu_date" => $menuRow['menu_date']
            ];
        }

        $stalls[] = [
            "stall_id" => $stall['stall_id'],
            "stallname" => $stall['stallname'],
            "ownername" => $stall['ownername'],
            "phonenumber" => $stall['phonenumber'],
            "email" => $stall['email'],
            "fulladdress" => $stall['fulladdress'],
            "fssainumber" => $stall['fssainumber'],
            "latitude" => $stall['latitude'],
            "longitude" => $stall['longitude'],
            "approval" => $stall['approval'],
            "menu" => $menu
        ];

        $menuStmt->close();
    }

    echo json_encode([
        "status" => "success",
        "menu_date" => $menu_date,
        "stalls" => $stalls
    ]);

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
