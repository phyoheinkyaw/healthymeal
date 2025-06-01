<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Basic logging function for COD verification
function logCodVerification($message, $data = null) {
    try {
        $log_dir = $_SERVER['DOCUMENT_ROOT'] . '/hm/uploads/logs/';
        
        if (!file_exists($log_dir)) {
            if (!mkdir($log_dir, 0777, true)) {
                error_log("Failed to create log directory: " . $log_dir);
                return false;
            }
        }
        
        $log_file = $log_dir . 'cod_verification.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $log_message .= ": " . print_r($data, true);
            } else {
                $log_message .= ": " . $data;
            }
        }

        file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
        return true;
    } catch (Exception $e) {
        error_log("Exception in logCodVerification: " . $e->getMessage());
        return false;
    }
}

// Log the initial request
logCodVerification("=== NEW COD PAYMENT VERIFICATION REQUEST ===");

// Validate required fields
if (!isset($data['order_id']) || !is_numeric($data['order_id'])) {
    http_response_code(400);
    logCodVerification("ERROR: Invalid order ID");
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$order_id = (int)$data['order_id'];
$verify = isset($data['verify']) ? (bool)$data['verify'] : true;
$verification_notes = $data['verification_notes'] ?? '';
$collected_amount = isset($data['collected_amount']) ? (int)$data['collected_amount'] : 0;
$payment_receipt_number = $data['payment_receipt_number'] ?? '';

// Get admin ID for verification records
$adminId = $_SESSION['user_id'] ?? 0;

if (!$adminId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Check if order exists and is a COD order
    $stmt = $mysqli->prepare("
        SELECT 
            o.order_id, 
            o.payment_method_id, 
            o.total_amount,
            ps.payment_method,
            ph.payment_id,
            ph.payment_status as current_payment_status
        FROM orders o
        LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
        LEFT JOIN payment_history ph ON o.order_id = ph.order_id
        WHERE o.order_id = ? AND ps.payment_method = 'Cash on Delivery'
        ORDER BY ph.created_at DESC
        LIMIT 1
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Order not found or not a Cash on Delivery order');
    }
    
    $order = $result->fetch_assoc();
    $orderTotalAmount = $order['total_amount'];
    $payment_id = $order['payment_id'] ?? null;
    
    $stmt->close();

    // Check if there's already a verification record for this order
    $check_verification = $mysqli->prepare("
        SELECT * FROM payment_verifications 
        WHERE order_id = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $check_verification->bind_param("i", $order_id);
    $check_verification->execute();
    $verification_result = $check_verification->get_result();
    $has_verification = $verification_result->num_rows > 0;
    $existing_verification = $has_verification ? $verification_result->fetch_assoc() : null;
    
    // If unverifying, check if it was previously verified
    if (!$verify && (!$has_verification || ($has_verification && $existing_verification['payment_verified'] != 1))) {
        throw new Exception('This payment has not been verified yet');
    }

    // Set payment status based on verification
    $payment_status = $verify ? 1 : 0; // 1 = completed, 0 = pending
    
    // If collected amount doesn't match, add it to notes
    if ($verify && $collected_amount > 0 && $collected_amount != $orderTotalAmount) {
        $verification_notes .= "\nCollected amount: {$collected_amount} MMK. Order total: {$orderTotalAmount} MMK.";
    }
    
    // Update payment history status
    if ($payment_id) {
        $updatePaymentStmt = $mysqli->prepare("
            UPDATE payment_history 
            SET payment_status = ?,
                transaction_id = ?,
                updated_at = NOW()
            WHERE payment_id = ?
        ");
        if ($updatePaymentStmt) {
            $updatePaymentStmt->bind_param("isi", $payment_status, $payment_receipt_number, $payment_id);
            $updatePaymentStmt->execute();
            $updatePaymentStmt->close();
        }
    }
    
    // Update or insert verification details
    if ($has_verification) {
        // Update existing verification
        $updateVerificationStmt = $mysqli->prepare("
            UPDATE payment_verifications 
            SET payment_status = ?,
                payment_verified = ?,
                verification_notes = ?,
                transaction_id = ?,
                amount_verified = ?,
                verified_by_id = ?,
                updated_at = NOW()
                " . ($verify ? ", payment_verified_at = NOW()" : "") . "
            WHERE verification_id = ?
        ");
        
        $payment_verified = $verify ? 1 : 0;
        $amount_to_verify = $verify ? ($collected_amount > 0 ? $collected_amount : $orderTotalAmount) : 0;
        
        $updateVerificationStmt->bind_param(
            "iissdii",
            $payment_status,
            $payment_verified,
            $verification_notes,
            $payment_receipt_number,
            $amount_to_verify,
            $adminId,
            $existing_verification['verification_id']
        );
        
        $updateVerificationStmt->execute();
        $updateVerificationStmt->close();
    } else {
        // Create a new verification record
        $insertVerificationStmt = $mysqli->prepare("
            INSERT INTO payment_verifications (
                payment_id,
                order_id,
                verified_by_id,
                payment_status,
                payment_verified,
                payment_verified_at,
                verification_notes,
                transaction_id,
                amount_verified,
                verification_attempt,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, " . 
                ($verify ? "NOW()" : "NULL") . 
                ", ?, ?, ?, 1, NOW(), NOW()
            )
        ");
        
        $payment_verified = $verify ? 1 : 0;
        $amount_to_verify = $verify ? ($collected_amount > 0 ? $collected_amount : $orderTotalAmount) : 0;
        
        $insertVerificationStmt->bind_param(
            "iiiissd",
            $payment_id,
            $order_id,
            $adminId,
            $payment_status,
            $payment_verified,
            $verification_notes,
            $payment_receipt_number,
            $amount_to_verify
        );
        
        $insertVerificationStmt->execute();
        $insertVerificationStmt->close();
    }

    // Create notification for the user
    $notificationMessage = $verify 
        ? "Your Cash on Delivery payment has been verified as received." 
        : "Your Cash on Delivery payment verification has been reset.";
    
    // Insert notification
    $userStmt = $mysqli->prepare("SELECT user_id FROM orders WHERE order_id = ?");
    $userStmt->bind_param("i", $order_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        $user_id = $user['user_id'];
        
        $insertNotificationStmt = $mysqli->prepare("
            INSERT INTO order_notifications (
                order_id,
                user_id,
                message,
                note,
                is_read,
                created_at
            ) VALUES (
                ?, ?, ?, ?, 0, NOW()
            )
        ");
        
        $insertNotificationStmt->bind_param(
            "iiss",
            $order_id,
            $user_id,
            $notificationMessage,
            $verification_notes
        );
        
        $insertNotificationStmt->execute();
        $insertNotificationStmt->close();
    }
    $userStmt->close();
    
    // Commit transaction
    $mysqli->commit();
    
    // Send response
    echo json_encode([
        'success' => true, 
        'message' => $verify 
            ? 'Cash on Delivery payment has been verified successfully.' 
            : 'Cash on Delivery payment verification has been reset.'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($mysqli->inTransaction()) {
        $mysqli->rollback();
    }
    
    logCodVerification("ERROR: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 