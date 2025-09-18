<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Kolkata');

try {
    $stall_id = $_POST['stall_id'] ?? '';
    if (empty($stall_id)) {
        throw new Exception("Stall ID is required.");
    }

    // Fetch opening and closing hours from the stalldetails table
    $stmt = $conn->prepare("SELECT opening_hours, closing_hours FROM stalldetails WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result || !$result['opening_hours'] || !$result['closing_hours']) {
        // If there are no hours set for the stall, return an empty list.
        echo json_encode(['status' => 'success', 'time_slots' => []]);
        exit;
    }

    $opening_time = new DateTime($result['opening_hours']);
    $closing_time = new DateTime($result['closing_hours']);
    $now = new DateTime();
    $time_slots = [];

    // Start generating slots from the opening time
    $current_slot = clone $opening_time;

    // Loop until the current slot is before the closing time
    while ($current_slot < $closing_time) {
        // Only add slots that are in the future
        if ($current_slot > $now) {
            $time_slots[] = $current_slot->format('g:i A'); // Format as "3:30 PM"
        }
        // Move to the next 30-minute slot
        $current_slot->add(new DateInterval('PT30M'));
    }

    echo json_encode(['status' => 'success', 'time_slots' => $time_slots]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}

$conn->close();
?>