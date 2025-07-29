<?php
header('Content-Type: application/json');
include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // --- View balance ---
    $student_id = $_GET['student_id'] ?? '';

    if (empty($student_id)) {
        echo json_encode(["status" => "error", "message" => "student_id is required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT balance FROM wallet WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            "status" => "success",
            "student_id" => $student_id,
            "balance" => $row['balance']
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Wallet not found"]);
    }

} elseif ($method === 'POST') {
    // --- Add money ---
    $student_id = $_POST['student_id'] ?? '';
    $amount = $_POST['amount'] ?? '';

    if (empty($student_id) || !is_numeric($amount)) {
        echo json_encode(["status" => "error", "message" => "student_id and numeric amount required"]);
        exit;
    }

    $amount = (float)$amount;

    // Check if wallet exists
    $stmt = $conn->prepare("SELECT balance FROM wallet WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Update existing wallet
        $new_balance = $row['balance'] + $amount;
        $update = $conn->prepare("UPDATE wallet SET balance = ? WHERE student_id = ?");
        $update->bind_param("ds", $new_balance, $student_id);
        $update->execute();
    } else {
        // Create new wallet
        $new_balance = $amount;
        $insert = $conn->prepare("INSERT INTO wallet (student_id, balance) VALUES (?, ?)");
        $insert->bind_param("sd", $student_id, $new_balance);
        $insert->execute();
    }

    // ✅ Log the top-up in wallet_topups table
    $log = $conn->prepare("INSERT INTO wallet_topups (student_id, amount) VALUES (?, ?)");
    $log->bind_param("sd", $student_id, $amount);
    $log->execute();

    echo json_encode([
        "status" => "success",
        "message" => "Amount added successfully",
        "new_balance" => $new_balance
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}
