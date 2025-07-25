<?php
header('Content-Type: application/json');
include 'config.php';

$user_lat = $_POST['latitude'] ?? '';
$user_long = $_POST['longitude'] ?? '';

if (empty($user_lat) || empty($user_long)) {
    echo json_encode(["status" => "error", "message" => "Latitude and longitude required"]);
    exit;
}

try {
    $today_date = date('Y-m-d');

    // Join MenuDetails (md) and StallDetails (sd) to get stallname
    $stmt = $conn->prepare("
        SELECT md.stall_id, sd.stallname, sd.latitude, sd.longitude, md.todays_special_name, md.todays_special_price
        FROM MenuDetails md
        JOIN StallDetails sd ON md.stall_id = sd.stall_id
        WHERE md.menu_date = ? AND md.working = 1
    ");
    $stmt->bind_param("s", $today_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $nearby_stalls = [];

    while ($row = $result->fetch_assoc()) {
        // Calculate distance using Haversine formula
        $earth_radius = 6371; // km

        $dLat = deg2rad($row['latitude'] - $user_lat);
        $dLon = deg2rad($row['longitude'] - $user_long);

        $lat1 = deg2rad($user_lat);
        $lat2 = deg2rad($row['latitude']);

        $a = sin($dLat/2) * sin($dLat/2) +
             sin($dLon/2) * sin($dLon/2) * cos($lat1) * cos($lat2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earth_radius * $c;

        if ($distance <= 5) { // within 5 km
            $row['distance_km'] = round($distance, 5);
            $nearby_stalls[] = $row;
        }
    }

    if (count($nearby_stalls) == 0) {
        echo json_encode(["status" => "success", "message" => "No nearby stalls found today", "nearby_stalls" => []]);
    } else {
        echo json_encode(["status" => "success", "nearby_stalls" => $nearby_stalls]);
    }

    $stmt->close();
    $conn->close();

} catch (mysqli_sql_exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed: " . $e->getMessage()]);
}
?>
