<?php
header('Content-Type: application/json');
include 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- FILE UPLOAD AND HELPER FUNCTIONS ---
function handleFileUpload($fileInfo, $itemName, $stall_id) {
    if (isset($fileInfo) && $fileInfo['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $extension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $safeItemName = preg_replace('/[^A-Za-z0-9]/', '', $itemName);
        $fileName = $stall_id . '_' . $safeItemName . '_' . time() . '.' . $extension;
        $uploadFile = $uploadDir . $fileName;
        if (move_uploaded_file($fileInfo['tmp_name'], $uploadFile)) { return $fileName; }
    }
    return null;
}
function deleteImageFile($filename) {
    if ($filename && file_exists('uploads/' . $filename)) { unlink('uploads/' . $filename); }
}
function isStallApproved($conn, $stall_id) {
    $stmt = $conn->prepare("SELECT approval FROM stalldetails WHERE stall_id = ?");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($result && $result['approval'] == 1);
}
function clearExistingSpecial($conn, $stall_id) {
    $stmt = $conn->prepare("UPDATE menudetails SET item_category = 'Regular' WHERE stall_id = ? AND item_category = 'Today\'s Special'");
    $stmt->bind_param("s", $stall_id);
    $stmt->execute();
    $stmt->close();
}

// --- ACTION ROUTER ---
$action = $_POST['action'] ?? '';
if (empty($action)) { die(json_encode(["status" => "error", "message" => "Action not specified."])); }

try {
    if ($action === 'get_menu_details') {
        handle_get_menu_details($conn);
    } else {
        $stall_id = $_POST['stall_id'] ?? '';
        if ($action === 'update_item' || $action === 'delete_item') {
            $item_id = $_POST['item_id'] ?? 0;
            $stmt_sid = $conn->prepare("SELECT stall_id FROM menudetails WHERE item_id = ?");
            $stmt_sid->bind_param("i", $item_id); $stmt_sid->execute();
            $stall_id = $stmt_sid->get_result()->fetch_assoc()['stall_id'] ?? '';
            $stmt_sid->close();
        }

        if (empty($stall_id) || !isStallApproved($conn, $stall_id)) {
            die(json_encode(["status" => "error", "message" => "Action denied. Stall is not approved."]));
        }

        switch ($action) {
            case 'add_item': handle_add_item($conn); break;
            case 'update_item': handle_update_item($conn); break;
            case 'delete_item': handle_delete_item($conn); break;
            case 'update_stall_status': handle_update_stall_status($conn); break;
            default: echo json_encode(["status" => "error", "message" => "Invalid action."]);
        }
    }
} catch (Exception $e) { 
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]); 
}

// --- ACTION HANDLER FUNCTIONS ---
function handle_get_menu_details($conn) {
    $stall_id = $_POST['stall_id'] ?? '';
    $response = [];
    $stmt_stall = $conn->prepare("SELECT approval, is_open_today, opening_hours, closing_hours FROM stalldetails WHERE stall_id = ?");
    $stmt_stall->bind_param("s", $stall_id); $stmt_stall->execute();
    $response['stall_details'] = $stmt_stall->get_result()->fetch_assoc();
    $stmt_stall->close();
    $stmt_menu = $conn->prepare("SELECT item_id, item_name, item_price, item_category, item_image FROM menudetails WHERE stall_id = ? ORDER BY item_category = 'Today\'s Special' DESC, item_id DESC");
    $stmt_menu->bind_param("s", $stall_id); $stmt_menu->execute();
    $response['menu_items'] = $stmt_menu->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_menu->close();
    echo json_encode(["status" => "success", "data" => $response]);
}

function handle_add_item($conn) {
    try {
        $stall_id = $_POST['stall_id'];
        $item_name = $_POST['item_name'];
        $item_category = $_POST['item_category'];
        if ($item_category === 'Today\'s Special') { clearExistingSpecial($conn, $stall_id); }
        
        $image_filename = isset($_FILES['item_image']) ? handleFileUpload($_FILES['item_image'], $item_name, $stall_id) : null;
        $stmt = $conn->prepare("INSERT INTO menudetails (stall_id, item_name, item_price, item_category, item_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $stall_id, $item_name, $_POST['item_price'], $item_category, $image_filename);
        $stmt->execute();
        if ($stmt->affected_rows > 0) { echo json_encode(["status" => "success", "message" => "Item added successfully."]); } 
        else { throw new Exception("Database did not report any new rows added."); }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}

function handle_update_item($conn) {
    try {
        $item_id = $_POST['item_id'];
        $item_name = $_POST['item_name'];
        $item_category = $_POST['item_category'];
        $stall_id = $_POST['stall_id'];
        
        if ($item_category === 'Today\'s Special') { clearExistingSpecial($conn, $stall_id); }

        $image_filename = $_POST['existing_image_url'] ?? null;
        if (isset($_FILES['item_image'])) {
            $stmt_old = $conn->prepare("SELECT item_image FROM menudetails WHERE item_id = ?");
            $stmt_old->bind_param("i", $item_id); $stmt_old->execute();
            deleteImageFile($stmt_old->get_result()->fetch_assoc()['item_image']);
            $stmt_old->close();
            $image_filename = handleFileUpload($_FILES['item_image'], $item_name, $stall_id);
        }

        $stmt = $conn->prepare("UPDATE menudetails SET item_name = ?, item_price = ?, item_category = ?, item_image = ? WHERE item_id = ?");
        $stmt->bind_param("sdssi", $item_name, $_POST['item_price'], $item_category, $image_filename, $item_id);
        $stmt->execute();
        echo json_encode(["status" => "success", "message" => "Item updated successfully."]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}

function handle_delete_item($conn) {
    try {
        $item_id = $_POST['item_id'];
        $stmt_old = $conn->prepare("SELECT item_image FROM menudetails WHERE item_id = ?");
        $stmt_old->bind_param("i", $item_id); $stmt_old->execute();
        deleteImageFile($stmt_old->get_result()->fetch_assoc()['item_image']);
        $stmt_old->close();
        
        $stmt = $conn->prepare("DELETE FROM menudetails WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) { echo json_encode(["status" => "success", "message" => "Item deleted successfully."]); } 
        else { throw new Exception("Item not found or already deleted."); }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}

function handle_update_stall_status($conn) {
    try {
        $stall_id = $_POST['stall_id'] ?? '';
        $is_open_today = $_POST['is_open_today'] ?? '1';
        $opening_hours_text = $_POST['opening_hours'] ?? '';
        $closing_hours_text = $_POST['closing_hours'] ?? '';

        $opening_hours_db = !empty($opening_hours_text) ? date("H:i:s", strtotime($opening_hours_text)) : null;
        $closing_hours_db = !empty($closing_hours_text) ? date("H:i:s", strtotime($closing_hours_text)) : null;

        $stmt = $conn->prepare("UPDATE stalldetails SET is_open_today = ?, opening_hours = ?, closing_hours = ? WHERE stall_id = ?");
        $stmt->bind_param("isss", $is_open_today, $opening_hours_db, $closing_hours_db, $stall_id);
        $stmt->execute();
        echo json_encode(["status" => "success", "message" => "Status updated successfully."]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}
?>  