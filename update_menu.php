<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Get POST data
    $stall_id = $_POST['stall_id'] ?? '';
    $working = $_POST['working'] ?? '';
    $todays_special_name = $_POST['todays_special_name'] ?? '';
    $todays_special_price = $_POST['todays_special_price'] ?? '';
    $todays_special_quantity = $_POST['todays_special_quantity'] ?? '';
    $opening_hours = $_POST['opening_hours'] ?? '';
    $closing_hours = $_POST['closing_hours'] ?? '';
    $break_start = $_POST['break_start'] ?? '';
    $break_end = $_POST['break_end'] ?? '';

    // Encode JSON fields
    $regular_menu = isset($_POST['regular_menu']) ? (is_array($_POST['regular_menu']) ? json_encode($_POST['regular_menu']) : $_POST['regular_menu']) : '[]';
    $combo_details = isset($_POST['combo_details']) ? (is_array($_POST['combo_details']) ? json_encode($_POST['combo_details']) : $_POST['combo_details']) : '[]';

    $today_date = date('Y-m-d');

    if (empty($stall_id)) {
        echo json_encode(["status" => "error", "message" => "stall_id is required"]);
        exit;
    }

 if ($working === '0') {
    // Check if already inserted for today
    $check = $conn->prepare("SELECT * FROM MenuDetails WHERE stall_id = ? AND menu_date = ?");
    $check->bind_param("ss", $stall_id, $today_date);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Stall already marked as not working today."]);
    } else {
        // Insert as not working with nulls for others
        $insert = $conn->prepare("
            INSERT INTO MenuDetails (
                stall_id, working, todays_special_name, todays_special_price, todays_special_quantity, 
                regular_menu, combo_details, opening_hours, closing_hours, break_start, break_end, menu_date
            ) VALUES (?, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?)
        ");
        $insert->bind_param("ss", $stall_id, $today_date);
        $insert->execute();

        echo json_encode(["status" => "success", "message" => "Stall not working today. Menu recorded as closed."]);
    }

    exit;
}

    

    // Check if menu exists for today
    $check = $conn->prepare("SELECT * FROM MenuDetails WHERE stall_id = ? AND menu_date = ?");
    $check->bind_param("ss", $stall_id, $today_date);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Menu exists - check for changes
        $row = $result->fetch_assoc();

        if (
            $row['working'] == $working &&
            $row['todays_special_name'] == $todays_special_name &&
            $row['todays_special_price'] == $todays_special_price &&
            $row['todays_special_quantity'] == $todays_special_quantity &&
            $row['regular_menu'] == $regular_menu &&
            $row['combo_details'] == $combo_details &&
            $row['opening_hours'] == $opening_hours &&
            $row['closing_hours'] == $closing_hours &&
            $row['break_start'] == $break_start &&
            $row['break_end'] == $break_end
        ) {
            echo json_encode(["status" => "success", "message" => "no changes made"]);
        } else {
            // Update menu
            $update = $conn->prepare("
                UPDATE MenuDetails 
                SET working = ?, 
                    todays_special_name = ?, 
                    todays_special_price = ?, 
                    todays_special_quantity = ?, 
                    regular_menu = ?, 
                    combo_details = ?, 
                    opening_hours = ?, 
                    closing_hours = ?, 
                    break_start = ?, 
                    break_end = ?
                WHERE stall_id = ? AND menu_date = ?
            ");
            $update->bind_param(
                "isdissssssss",
                $working,
                $todays_special_name,
                $todays_special_price,
                $todays_special_quantity,
                $regular_menu,
                $combo_details,
                $opening_hours,
                $closing_hours,
                $break_start,
                $break_end,
                $stall_id,
                $today_date
            );
            $update->execute();

            echo json_encode(["status" => "success", "message" => "menu updated"]);
        }
    } else {
        // Insert new menu
        $insert = $conn->prepare("
            INSERT INTO MenuDetails (
                stall_id, working, todays_special_name, todays_special_price, todays_special_quantity, 
                regular_menu, combo_details, opening_hours, closing_hours, break_start, break_end, menu_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param(
            "sisdisssssss",
            $stall_id,
            $working,
            $todays_special_name,
            $todays_special_price,
            $todays_special_quantity,
            $regular_menu,
            $combo_details,
            $opening_hours,
            $closing_hours,
            $break_start,
            $break_end,
            $today_date
        );
        $insert->execute();

        echo json_encode(["status" => "success", "message" => "menu inserted"]);
    }

    $check->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Operation failed: " . $e->getMessage()]);
}
?>
