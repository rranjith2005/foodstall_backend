<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    die(json_encode([
        "status" => "error", "message" => "PHP Error: " . $message,
        "file" => $file, "line" => $line
    ]));
});

include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$stall_id = $_POST['stall_id'] ?? '';
$student_id = $_POST['student_id'] ?? '';

if (empty($stall_id) || empty($student_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Stall ID and Student ID are required.']);
    exit;
}

try {
    $response = [];

    // --- UPDATED: This query now also selects latitude and longitude ---
    $stmt_stall = $conn->prepare("SELECT stallname, fulladdress, latitude, longitude FROM stalldetails WHERE stall_id = ?");
    $stmt_stall->bind_param("s", $stall_id);
    $stmt_stall->execute();
    $response['stall_details'] = $stmt_stall->get_result()->fetch_assoc();
    $stmt_stall->close();

    // 2. Check if the stall is a favorite (Unchanged)
    $stmt_fav = $conn->prepare("SELECT COUNT(*) as count FROM favorite_stalls WHERE student_id = ? AND stall_id = ?");
    $stmt_fav->bind_param("ss", $student_id, $stall_id);
    $stmt_fav->execute();
    $fav_result = $stmt_fav->get_result()->fetch_assoc();
    $response['is_favorite'] = ($fav_result && $fav_result['count'] > 0);
    $stmt_fav->close();

    // 3. Fetch all menu items for the stall from the permanent menu table
    $stmt_menu = $conn->prepare(
        "SELECT item_name as name, item_price as price, item_category, item_image as image 
         FROM menudetails 
         WHERE stall_id = ? AND is_available = 1"
    );
    $stmt_menu->bind_param("s", $stall_id);
    $stmt_menu->execute();
    $all_menu_items = $stmt_menu->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_menu->close();
    
    // 4. In PHP, separate the specials from the full menu
    $response['todays_special'] = null;
    $full_menu = [];
    foreach ($all_menu_items as $item) {
        if ($item['item_category'] == 'Today\'s Special') {
            $response['todays_special'] = $item;
        } else {
            $full_menu[] = $item;
        }
    }
    $response['full_menu'] = $full_menu;

    // 5. Get The Most Popular Dish for THIS stall
    $stmt_popular = $conn->prepare(
        "SELECT 
            oi.item_name as name,
            md.item_image as image,
            oi.price as price,
            SUM(oi.quantity) as sold_count
         FROM order_items oi
         JOIN orders o ON oi.order_id = o.order_id
         LEFT JOIN menudetails md ON o.stall_id = md.stall_id AND oi.item_name = md.item_name
         WHERE o.stall_id = ?
         GROUP BY oi.item_name, md.item_image, oi.price
         ORDER BY sold_count DESC
         LIMIT 1"
    );
    $stmt_popular->bind_param("s", $stall_id);
    $stmt_popular->execute();
    $response['popular_dish'] = $stmt_popular->get_result()->fetch_assoc();
    $stmt_popular->close();
    
    // 6. Get Customer Reviews (Unchanged)
    $stmt_reviews = $conn->prepare(
        "SELECT r.student_id, u.fullname, r.rating, r.review_text, r.review_date
         FROM student_reviews r
         JOIN usignup u ON r.student_id = u.student_id
         WHERE r.stall_id = ?
         ORDER BY r.review_date DESC"
    );
    $stmt_reviews->bind_param("s", $stall_id);
    $stmt_reviews->execute();
    $response['reviews'] = $stmt_reviews->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reviews->close();

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>