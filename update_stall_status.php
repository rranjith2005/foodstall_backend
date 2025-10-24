<?php
// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP; // <-- REQUIRED FOR DEBUGGING
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer.php';
require 'SMTP.php';
require 'Exception.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
include 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// [NEW] Add a log to see if the file is even being called
error_log("--- update_stall_status.php received a request ---");

try {
    $email = $_POST['email'] ?? '';
    $status = $_POST['status'] ?? null;
    $reason = $_POST['reason'] ?? null;

    error_log("Received data: email=$email, status=$status"); // Log received data

    if (empty($email) || !in_array($status, ['-1', '0', '1'], true)) {
        throw new Exception("Invalid email or status provided");
    }

    $check_stmt = $conn->prepare("SELECT stall_id, stallname FROM stalldetails WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Stall not found for the given email");
    }
    $stall_data = $result->fetch_assoc();
    $check_stmt->close();
    
    $stall_name = $stall_data['stallname'];

    if ($status === '1') {
        // --- APPROVE STALL ---
        error_log("Attempting to approve stall for $email"); // Log action

        if (!empty($stall_data['stall_id'])) {
             throw new Exception("This stall has already been processed.");
        }
        
        $generated_stall_id = null;
        while(true) {
            $random_part = mt_rand(10000, 99999);
            $stall_id_candidate = 'S' . $random_part;

            $id_check_stmt = $conn->prepare("SELECT stall_id FROM stalldetails WHERE stall_id = ?");
            $id_check_stmt->bind_param("s", $stall_id_candidate);
            $id_check_stmt->execute();
            $id_result = $id_check_stmt->get_result();
            
            if ($id_result->num_rows == 0) {
                $generated_stall_id = $stall_id_candidate;
                $id_check_stmt->close();
                break; 
            }
            $id_check_stmt->close();
        }

        $update_stmt = $conn->prepare("UPDATE stalldetails SET approval = 1, stall_id = ?, rejection_reason = NULL WHERE email = ?");
        $update_stmt->bind_param("ss", $generated_stall_id, $email);
        $update_stmt->execute();
        
        try {
            error_log("Entering APPROVAL email block for $email"); // Log email attempt
            $mail = new PHPMailer(true);

            // --- [NEW] ADDED DEBUGGING ---
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug ($level): $str");
            };
            // --- END DEBUGGING ---

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ranjithsuriya12345@gmail.com'; // Your email
            $mail->Password = 'bkmm frmv ajem nyra'; // Your 16-digit App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->setFrom('ranjithsuriya12345@gmail.com', 'Stall Spot Admin');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Congratulations! Your Stall Has Been Approved';
            $mail->Body    = "<html><body><h2>Welcome to Stall Spot!</h2><p>We are excited to inform you that your stall, '<b>" . htmlspecialchars($stall_name) . "</b>', has been approved.</p><p>Your unique <b>Owner ID</b> is: <b>" . $generated_stall_id . "</b></p><p>You can now log in to the Stall Spot app using this ID (or your phone number) and your password to manage your stall.</p></body></html>";
            
            $mail->send();
            error_log("Approval email SENT successfully to $email"); // Log success

        } catch (Exception $e) {
            error_log("Approval email FAILED for " . $email . ": " . $mail->ErrorInfo);
        }

        echo json_encode(["status" => "success", "message" => "Stall approved and notification sent.", "stall_id" => $generated_stall_id]);

    } elseif ($status === '-1') {
        // --- REJECT STALL ---
        error_log("Attempting to REJECT stall for $email"); // Log action

        if (empty($reason)) {
            $reason = "Rejected by admin without a specific reason.";
        }
        $update_stmt = $conn->prepare("UPDATE stalldetails SET approval = -1, stall_id = NULL, rejection_reason = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $reason, $email);
        $update_stmt->execute();

        // --- SEND REJECTION EMAIL ---
        try {
            error_log("Entering REJECTION email block for $email"); // Log email attempt
            $mail = new PHPMailer(true);
            
            // --- [NEW] ADDED DEBUGGING ---
            $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer Debug ($level): $str");
            };
            // --- END DEBUGGING ---

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ranjithsuriya12345@gmail.com'; 
            $mail->Password = 'bkmm frmv ajem nyra'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->setFrom('ranjithsuriya12345@gmail.com', 'Stall Spot Admin');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'An Update on Your Stall Application';
            $mail->Body    = "<html><body><h2>Stall Spot Application Update</h2><p>We are writing to inform you about the status of your stall, '<b>" . htmlspecialchars($stall_name) . "</b>'.</p><p>After careful review, we regret to inform you that your application has been rejected.</p><p><b>Reason for Rejection:</b> " . htmlspecialchars($reason) . "</p><p>Thank you for your interest in Stall Spot.</p></body></html>";
            
            $mail->send();
            error_log("Rejection email SENT successfully to $email"); // Log success

        } catch (Exception $e) {
            error_log("Rejection email FAILED for " . $email . ": " . $mail->ErrorInfo);
        }

        echo json_encode(["status" => "success", "message" => "Stall has been rejected"]);

    } elseif ($status === '0') {
        // --- SET TO PENDING ---
        $update_stmt = $conn->prepare("UPDATE stalldetails SET approval = 0, stall_id = NULL, rejection_reason = NULL WHERE email = ?");
        $update_stmt->bind_param("s", $email);
        $update_stmt->execute();
        echo json_encode(["status" => "success", "message" => "Stall status has been reset to pending"]);
    }
    
    if (isset($update_stmt)) {
        $update_stmt->close();
    }
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
}
?>