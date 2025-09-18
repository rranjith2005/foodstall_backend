<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $student_id = $_POST['student_id'] ?? '';
    if (empty($student_id)) {
        throw new Exception("Student ID is required.");
    }

    $response = [];

    // 1. Get User's Full Name (Unchanged)
    $stmt_user = $conn->prepare("SELECT fullname FROM usignup WHERE student_id = ?");
    $stmt_user->bind_param("s", $student_id);
    $stmt_user->execute();
    $user_result = $stmt_user->get_result()->fetch_assoc();
    $response['user_fullname'] = $user_result['fullname'] ?? 'User';
    $stmt_user->close();

    // 2. Get Today's Specials (Unchanged)
    $stmt_specials = $conn->prepare(
        "SELECT 
            sd.stall_id, sd.stallname AS stall_name, 
            md.item_name AS todays_special_name, md.item_price AS todays_special_price, md.item_image AS todays_special_image 
         FROM menudetails md 
         JOIN stalldetails sd ON md.stall_id = sd.stall_id 
         WHERE sd.approval = 1 AND sd.is_open_today = 1 AND md.item_category = 'Today\'s Special'"
    );
    $stmt_specials->execute();
    $response['specials'] = $stmt_specials->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_specials->close();

    // 3. Get All Approved Food Stalls (UPDATED to format time)
    $stmt_stalls = $conn->prepare(
        "SELECT 
            sd.*, 
            sd.is_open_today as isOpen,
            COALESCE(avg_ratings.avg_rating, 0.0) as rating,
            IF(fav.student_id IS NOT NULL, 1, 0) as isFavorite,
            TIME_FORMAT(sd.opening_hours, '%l:%i %p') as opening_hours,
            TIME_FORMAT(sd.closing_hours, '%l:%i %p') as closing_hours
         FROM stalldetails sd 
         LEFT JOIN (
             SELECT stall_id, AVG(rating) as avg_rating FROM student_reviews GROUP BY stall_id
         ) as avg_ratings ON sd.stall_id = avg_ratings.stall_id
         LEFT JOIN favorite_stalls fav ON sd.stall_id = fav.stall_id AND fav.student_id = ?
         WHERE sd.approval = 1"
    );
    $stmt_stalls->bind_param("s", $student_id);
    $stmt_stalls->execute();
    $response['stalls'] = $stmt_stalls->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_stalls->close();

    // 4. Get Popular Dish For Each Stall (UPDATED with new logic)
    $stmt_popular = $conn->prepare(
       "WITH RankedItems AS (
            SELECT
                oi.item_name,
                oi.price,
                o.stall_id,
                sd.stallname as stall_name,
                md.item_image,
                ROW_NUMBER() OVER(PARTITION BY o.stall_id ORDER BY SUM(oi.quantity * oi.price) DESC) as rn
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN stalldetails sd ON o.stall_id = sd.stall_id
            LEFT JOIN menudetails md ON o.stall_id = md.stall_id AND oi.item_name = md.item_name
            WHERE sd.approval = 1 AND sd.is_open_today = 1
            GROUP BY o.stall_id, oi.item_name, oi.price, sd.stallname, md.item_image
        )
        SELECT stall_id, stall_name, item_name, price, item_image FROM RankedItems WHERE rn = 1"
    );
    $stmt_popular->execute();
    $response['popular_dishes'] = $stmt_popular->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_popular->close();
    
    echo json_encode(['status' => 'success', 'data' => $response]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}

$conn->close();
?>