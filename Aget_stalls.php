<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

try {
    // Get filter from GET or POST (approved, rejected, all)
    $filter = $_GET['filter'] ?? $_POST['filter'] ?? 'all';

    if ($filter == 'approved') {
        $query = "SELECT * FROM stalldetails WHERE approval = 1";
    } elseif ($filter == 'rejected') {
        $query = "SELECT * FROM stalldetails WHERE approval = 0";
    } else {
        // all stalls
        $query = "SELECT * FROM stalldetails";
    }

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $stalls = [];

    while ($row = $result->fetch_assoc()) {
        $stalls[] = [
            "stall_id" => $row['stall_id'],
            "stallname" => $row['stallname'],
            "ownername" => $row['ownername'],
            "phonenumber" => $row['phonenumber'],
            "email" => $row['email'],
            "fulladdress" => $row['fulladdress'],
            "fssainumber" => $row['fssainumber'],
            "latitude" => $row['latitude'],
            "longitude" => $row['longitude'],
            "approval" => $row['approval']
        ];
    }

    echo json_encode([
        "status" => "success",
        "filter" => $filter,
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
