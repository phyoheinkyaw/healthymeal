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

// Validate required fields
if (!isset($data['order_id']) || !is_numeric($data['order_id'])) {
    http_response_code(400);
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
    $should_update = false;
    
    if ($payment_id) {
        // Check if a verification record already exists for this payment
        $check_existing = $mysqli->prepare("
            SELECT verification_id
            FROM payment_verifications
            WHERE payment_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $check_existing->bind_param("i", $payment_id);
        $check_existing->execute();
        $check_result = $check_existing->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_data = $check_result->fetch_assoc();
            $existing_verification_id = $existing_data['verification_id'];
            $should_update = true;
        }
        $check_existing->close();
    }
    
    if ($should_update) {
        // Update existing verification record
        $verificationStmt = $mysqli->prepare("
            UPDATE payment_verifications SET
                verified_by_id = ?,
                payment_status = ?,
                verification_notes = ?,
                transaction_id = ?,
                amount_verified = ?,
                payment_verified = ?,
                payment_verified_at = ?,
                verification_attempt = verification_attempt + 1,
                resubmission_status = ?,
                updated_at = NOW()
            WHERE verification_id = ?
        ");

        if ($verificationStmt) {
            $payment_verified = ($payment_status_to_set == 1) ? 1 : 0;
            $payment_verified_at = ($payment_status_to_set == 1) ? date('Y-m-d H:i:s') : null;
            
            // Set resubmission status:
            // - Use existing is_resubmission flag if provided
            // - Set to "pending resubmission" (2) when payment fails (status 2)
            // - Otherwise set to 0 (original)
            $resubmission_status = $is_resubmission ? 1 : 0;
            if ($payment_status_to_set == 2) {
                $resubmission_status = 2; // Set to "pending resubmission" when payment fails
            }
            
            $verificationStmt->bind_param("iississii", 
                $adminId,
                $payment_status_to_set,
                $verification_notes,
                $transaction_id,
                $amount_verified,
                $payment_verified,
                $payment_verified_at,
                $resubmission_status,
                $existing_verification_id
            );
            
            if (!$verificationStmt->execute()) {
                throw new Exception('Failed to update payment verification: ' . $verificationStmt->error);
            }
            
            $verification_id = $existing_verification_id;
        } else {
            throw new Exception('Database error when preparing update: ' . $mysqli->error);
        }
    } else {
        // Insert new verification record if no existing record found
        $verificationStmt = $mysqli->prepare("
            INSERT INTO payment_verifications (
                payment_id, 
                order_id, 
                verified_by_id, 
                payment_status, 
                verification_notes, 
                transaction_id,
                amount_verified,
                payment_verified,
                payment_verified_at,
                verification_attempt,
                resubmission_status,
                transfer_slip
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($verificationStmt) {
            $payment_verified = ($payment_status_to_set == 1) ? 1 : 0;
            $payment_verified_at = ($payment_status_to_set == 1) ? date('Y-m-d H:i:s') : null;
            $transfer_slip = $order['transfer_slip'] ?? null;
            
            // Set resubmission status:
            // - Use existing is_resubmission flag if provided
            // - Set to "pending resubmission" (2) when payment fails (status 2)
            // - Otherwise set to 0 (original)
            $resubmission_status = $is_resubmission ? 1 : 0;
            if ($payment_status_to_set == 2) {
                $resubmission_status = 2; // Set to "pending resubmission" when payment fails
            }
            
            $verificationStmt->bind_param("iiisissiisss", 
                $payment_id,
                $order_id,
                $adminId,
                $payment_status_to_set,
                $verification_notes,
                $transaction_id,
                $amount_verified,
                $payment_verified,
                $payment_verified_at,
                $attempt_count,
                $resubmission_status,
                $transfer_slip
            );
            
            if (!$verificationStmt->execute()) {
                throw new Exception('Failed to create payment verification: ' . $verificationStmt->error);
            }
            
            $verification_id = $mysqli->insert_id;
        } else {
            throw new Exception('Database error when preparing insert: ' . $mysqli->error);
        }
    }
    
    // Add a log entry for this verification action
    $logStmt = $mysqli->prepare("
        INSERT INTO payment_verification_logs (
            verification_id,
            order_id,
            status_changed_from,
            status_changed_to,
            amount,
            admin_notes,
            verified_by_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($logStmt) {
        // Use the actual status from before this operation
        $previous_status = $currentPaymentStatus;
        
        $logStmt->bind_param("iiiiisi",
            $verification_id,
            $order_id,
            $previous_status,
            $payment_status_to_set,
            $amount_verified,
            $verification_notes,
            $adminId
        );
        
        $logStmt->execute();
        $logStmt->close();
    }
    
    // Add notification with appropriate message
    $statusMessages = [
        0 => "Payment verification is pending",
        1 => "Payment has been verified successfully",
        2 => "Payment verification has been rejected",
        3 => "Payment has been refunded",
        4 => "Partial payment has been verified"
    ];
    
    $notificationMessage = $statusMessages[$payment_status_to_set] ?? "Payment status has been updated";
    
    // Get the user_id from the orders table
    $userIdStmt = $mysqli->prepare("SELECT user_id FROM orders WHERE order_id = ?");
    if ($userIdStmt) {
        $userIdStmt->bind_param("i", $order_id);
        $userIdStmt->execute();
        $userIdResult = $userIdStmt->get_result();
        if ($userIdRow = $userIdResult->fetch_assoc()) {
            $userId = $userIdRow['user_id'];
            
            // Insert notification
            $notificationStmt = $mysqli->prepare("
                INSERT INTO order_notifications (
                    order_id, 
                    user_id,
                    message, 
                    note
                ) VALUES (?, ?, ?, ?)
            ");
            
            if ($notificationStmt) {
                $notificationStmt->bind_param("iiss", 
                    $order_id,
                    $userId,
                    $notificationMessage,
                    $verification_notes
                );
                $notificationStmt->execute();
                $notificationStmt->close();
            }
        }
        $userIdStmt->close();
    }
    
    // Close verification statement if it's still open
    if (isset($verificationStmt) && $verificationStmt) {
        $verificationStmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $notificationMessage,
        'is_update' => $should_update
    ]);

    // If everything is successful, commit the transaction
    $mysqli->commit();

} catch (Exception $e) {
    // Something went wrong, rollback the transaction
    $mysqli->rollback();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 