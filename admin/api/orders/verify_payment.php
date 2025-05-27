<?php
require_once '../../../includes/auth_check.php';
require_once '../../../api/orders/utils/tax_calculator.php';

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

// Basic logging function for payment verification
function logVerification($message, $data = null) {
    try {
        $log_dir = $_SERVER['DOCUMENT_ROOT'] . '/hm/uploads/logs/';
        
        if (!file_exists($log_dir)) {
            if (!mkdir($log_dir, 0777, true)) {
                error_log("Failed to create log directory: " . $log_dir);
                return false;
            }
        }
        
        $log_file = $log_dir . 'payment_verification.log';
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
        error_log("Exception in logVerification: " . $e->getMessage());
        return false;
    }
}

// Log the initial request
logVerification("=== NEW PAYMENT VERIFICATION REQUEST ===");

// Validate required fields
if (!isset($data['order_id']) || !is_numeric($data['order_id'])) {
    http_response_code(400);
    logVerification("ERROR: Invalid order ID");
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$order_id = (int)$data['order_id'];
$verify = isset($data['verify']) ? (bool)$data['verify'] : true;

// Get admin ID for verification records
$adminId = $_SESSION['user_id'] ?? 0;

if (!$adminId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
    exit();
}

// Check if any previous verification exists
$check_verifications = $mysqli->prepare("
    SELECT * FROM payment_verifications 
    WHERE order_id = ? 
    ORDER BY created_at DESC
");
$check_verifications->bind_param("i", $order_id);
$check_verifications->execute();
$verifications_result = $check_verifications->get_result();
$has_previous_verification = $verifications_result->num_rows > 0;

// Get the verification attempt count
$attempt_count = 1; // Default to 1 for first attempt

if ($has_previous_verification) {
    $count_stmt = $mysqli->prepare("
        SELECT COUNT(*) as total_attempts 
        FROM payment_verifications 
        WHERE order_id = ?
    ");
    $count_stmt->bind_param("i", $order_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_data = $count_result->fetch_assoc();
    $attempt_count = (int)$count_data['total_attempts'] + 1; // Add 1 for current attempt
    $count_stmt->close();
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Check if order exists and get payment details
    $stmt = $mysqli->prepare("
        SELECT 
            o.order_id, 
            o.payment_method_id, 
            o.total_amount,
            ps.payment_method,
            ph.payment_id,
            ph.payment_status as current_payment_status,
            COALESCE(pv.payment_verified, 0) as payment_verified,
            COALESCE(pv.payment_verified_at, NULL) as payment_verified_at,
            (SELECT transfer_slip FROM payment_verifications WHERE order_id = o.order_id ORDER BY created_at DESC LIMIT 1) as transfer_slip
        FROM orders o
        LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
        LEFT JOIN payment_history ph ON o.order_id = ph.order_id
        LEFT JOIN payment_verifications pv ON ph.payment_id = pv.payment_id
        WHERE o.order_id = ?
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
        throw new Exception('Order not found');
    }
    
    $order = $result->fetch_assoc();
    $isCashOnDelivery = ($order['payment_method'] === 'Cash on Delivery');
    $orderTotalAmount = $order['total_amount'];
    $currentPaymentStatus = $order['current_payment_status'] ?? 0;
    $payment_id = $order['payment_id'] ?? null;

    // For non-COD orders, require a payment slip
    if (!$isCashOnDelivery && empty($order['transfer_slip'])) {
        throw new Exception('No payment slip found for this order');
    }

    // If unverifying, check if it was previously verified
    if (!$verify && $order['payment_verified'] != 1) {
        throw new Exception('This payment has not been verified yet');
    }
    
    $stmt->close();

    // Get payment status from verification details
    $payment_status = isset($data['verification_details']['payment_status']) ? 
        (int)$data['verification_details']['payment_status'] : 0;
    $verification_notes = $data['verification_details']['verification_notes'] ?? '';
    $transaction_id = $data['verification_details']['transaction_id'] ?? '';
    $is_resubmission = isset($data['verification_details']['is_resubmission']) ? 
        (bool)$data['verification_details']['is_resubmission'] : false;
    $is_refund = isset($data['verification_details']['is_refund']) ? 
        (bool)$data['verification_details']['is_refund'] : false;

    // ===========================================================================
    // REFUND PROCESS HANDLING
    // ===========================================================================
    
    // If is_refund flag is set or payment status is 3, process as refund
    if ($is_refund || $payment_status == 3) {
        $payment_status = 3; // Force refund status
        
        // If no verification notes provided for refund, set a default
        if (empty($verification_notes)) {
            $verification_notes = "Payment refunded by admin.";
        }
        
        // Force auto-generation of transaction ID if empty for refunds
        if (empty($transaction_id)) {
            $transaction_id = 'REFUND-' . $order_id . '-' . time();
        }
        
        // Process the refund (update order status, payment status, create verification)
        try {
            // 1. Update order status to cancelled (7)
            $updateOrderStmt = $mysqli->prepare("UPDATE orders SET status_id = 7, updated_at = NOW() WHERE order_id = ?");
            $updateOrderStmt->bind_param("i", $order_id);
            $updateOrderResult = $updateOrderStmt->execute();
            $updateOrderStmt->close();
            
            if (!$updateOrderResult) {
                throw new Exception("Failed to update order status: " . $mysqli->error);
            }
            
            // 2. Update payment history
            $updatePaymentStmt = $mysqli->prepare("UPDATE payment_history SET payment_status = 3, updated_at = NOW() 
                              WHERE order_id = ? ORDER BY payment_id DESC LIMIT 1");
            $updatePaymentStmt->bind_param("i", $order_id);
            $updatePaymentResult = $updatePaymentStmt->execute();
            $updatePaymentStmt->close();
            
            if (!$updatePaymentResult) {
                throw new Exception("Failed to update payment status: " . $mysqli->error);
            }
            
            // 3. Create or update payment verification record
            if ($has_previous_verification) {
                $lastVerification = $verifications_result->fetch_assoc();
                $verification_id = $lastVerification['verification_id'];
                
                // Update existing verification
                $updateVerificationStmt = $mysqli->prepare("
                    UPDATE payment_verifications SET 
                    payment_status = ?,
                    payment_verified = 1,
                    payment_verified_at = NOW(),
                    transaction_id = ?,
                    verification_notes = ?,
                    updated_at = NOW() 
                    WHERE verification_id = ?
                ");
                $updateVerificationStmt->bind_param("issi", $payment_status, $transaction_id, $verification_notes, $verification_id);
                $updateVerificationStmt->execute();
                $updateVerificationStmt->close();
            } else {
                // Insert new verification
                $insertVerificationStmt = $mysqli->prepare("
                    INSERT INTO payment_verifications (
                    payment_id, order_id, verified_by_id, payment_status,
                    verification_notes, transaction_id, payment_verified,
                    payment_verified_at, verification_attempt, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW(), NOW())
                ");
                $insertVerificationStmt->bind_param("iiisisi", $payment_id, $order_id, $adminId, 
                    $payment_status, $verification_notes, $transaction_id, $attempt_count);
                $insertVerificationStmt->execute();
                $verification_id = $mysqli->insert_id;
                $insertVerificationStmt->close();
            }
            
            // 4. Create notification for user about the refund
            $userStmt = $mysqli->prepare("SELECT user_id FROM orders WHERE order_id = ?");
            $userStmt->bind_param("i", $order_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            
            if ($userResult && $userResult->num_rows > 0) {
                $userRow = $userResult->fetch_assoc();
                $user_id = $userRow['user_id'];
                
                $message = "Your payment for order #{$order_id} has been refunded and your order has been cancelled.";
                
                $insertNotificationStmt = $mysqli->prepare("
                    INSERT INTO order_notifications (
                    order_id, user_id, message, note, is_read, created_at
                    ) VALUES (?, ?, ?, ?, 0, NOW())
                ");
                $insertNotificationStmt->bind_param("iiss", $order_id, $user_id, $message, $verification_notes);
                $insertNotificationStmt->execute();
                $insertNotificationStmt->close();
            }
            $userStmt->close();
            
            // Commit transaction
            $mysqli->commit();
            
            // Send success response for refund
            echo json_encode([
                'success' => true, 
                'message' => 'Payment has been refunded and order has been cancelled.'
            ]);
            exit;
        }
        catch (Exception $e) {
            $mysqli->rollback();
            logVerification("Refund failed: " . $e->getMessage());
            
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // ===========================================================================
    // STANDARD PAYMENT VERIFICATION
    // ===========================================================================
    
    // Ensure amount_verified is a whole number (MMK)
    $amount_verified = isset($data['verification_details']['amount_verified']) ? 
        (int)$data['verification_details']['amount_verified'] : 0;

    // Validate payment status
    if (!in_array($payment_status, [0, 1, 2, 3, 4])) {
        throw new Exception('Invalid payment status');
    }

    // Handle payment status logic
    $payment_status_to_set = $payment_status;
    if ($verify) {
        if ($payment_status == 1) { // Completed
            if ($amount_verified > 0 && $amount_verified != $orderTotalAmount) {
                // If verified amount doesn't match order total, mark as partial payment
                $payment_status_to_set = 4; // 4 = partial payment
                $verification_notes .= "\nAmount verified: {$amount_verified} MMK. Order total: {$orderTotalAmount} MMK.";
            } else if ($amount_verified == 0) {
                // If no amount provided but marked as verified, use order total amount
                $amount_verified = $orderTotalAmount;
            }
        } else if ($payment_status == 0) { // Pending
            $amount_verified = 0; // Reset amount for pending status
        }
    } else {
        // When unverifying, set everything back to pending
        $payment_status_to_set = 0;
        $amount_verified = 0;
        $verification_notes = 'Payment verification removed by admin';
    }

    // Update payment history status if payment_id exists
    if ($payment_id) {
        $updatePaymentStmt = $mysqli->prepare("
            UPDATE payment_history 
            SET payment_status = ?,
                updated_at = NOW()
            WHERE payment_id = ?
        ");
        if ($updatePaymentStmt) {
            $updatePaymentStmt->bind_param("ii", $payment_status_to_set, $payment_id);
            $updatePaymentStmt->execute();
            $updatePaymentStmt->close();
        }
    }

    // Update order status based on payment status
    $new_status_id = 1; // Default to pending (1)
    if ($payment_status_to_set == 1) {
        $new_status_id = 2; // Completed payment -> Confirmed (2) order
    } else if ($payment_status_to_set == 2) {
        $new_status_id = 1; // Failed payment -> Pending (1) order
    } else if ($payment_status_to_set == 3) {
        $new_status_id = 7; // Refunded payment -> Cancelled (7) order
    }
    // For partial payments (status 4) and pending (status 0), keep status as pending (1)

    $updateOrderStmt = $mysqli->prepare("
        UPDATE orders 
        SET status_id = ?, 
            updated_at = NOW() 
        WHERE order_id = ?
    ");
    
    if ($updateOrderStmt) {
        $updateOrderStmt->bind_param("ii", $new_status_id, $order_id);
        $updateOrderStmt->execute();
        $updateOrderStmt->close();
    }
    
    // Update or insert verification details
    $existing_verification_id = null;
    
    // If there's already a verification record, update it
    if ($has_previous_verification) {
        $verification = $verifications_result->fetch_assoc();
        $existing_verification_id = $verification['verification_id'];
        
        $updateVerificationStmt = $mysqli->prepare("
            UPDATE payment_verifications 
            SET payment_status = ?,
                payment_verified = ?,
                verification_notes = ?,
                transaction_id = ?,
                amount_verified = ?,
                updated_at = NOW()
                " . ($verify ? ", payment_verified_at = NOW()" : "") . "
            WHERE verification_id = ?
        ");
        
        $payment_verified = $verify ? 1 : 0;
        
        $updateVerificationStmt->bind_param(
            "iissdi",
            $payment_status_to_set,
            $payment_verified,
            $verification_notes,
            $transaction_id,
            $amount_verified,
            $existing_verification_id
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
                resubmission_status,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, " . 
                ($verify ? "NOW()" : "NULL") . 
                ", ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        $payment_verified = $verify ? 1 : 0;
        $resubmission_status = $is_resubmission ? 1 : 0;
        
        $insertVerificationStmt->bind_param(
            "iiiissdii",
            $payment_id,
            $order_id,
            $adminId,
            $payment_status_to_set,
            $payment_verified,
            $verification_notes,
            $transaction_id,
            $amount_verified,
            $attempt_count,
            $resubmission_status
        );
        
        $insertVerificationStmt->execute();
        $existing_verification_id = $mysqli->insert_id;
        $insertVerificationStmt->close();
    }

    // Create notification for success or failure
    $notificationMessage = '';
    
    if ($verify) {
        if ($payment_status_to_set == 1) {
            $notificationMessage = "Your payment has been verified successfully!";
        } else if ($payment_status_to_set == 2) {
            $notificationMessage = "Your payment verification has failed. Please check your payment details.";
        } else if ($payment_status_to_set == 3) {
            $notificationMessage = "Your payment has been refunded and your order has been cancelled.";
        } else if ($payment_status_to_set == 4) {
            $notificationMessage = "Your payment has been partially verified. Please check payment details.";
        }
    } else {
        $notificationMessage = "Your payment verification status has been reset.";
    }
    
    // Insert notification
    if (!empty($notificationMessage)) {
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
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Send response
    echo json_encode([
        'success' => true, 
        'message' => $notificationMessage
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($mysqli->inTransaction()) {
        $mysqli->rollback();
    }
    
    logVerification("ERROR: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 