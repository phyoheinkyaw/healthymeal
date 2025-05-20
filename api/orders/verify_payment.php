<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

// Get the request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data || !isset($data['order_id']) || !isset($data['verify'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$order_id = (int)$data['order_id'];
$verify = (bool)$data['verify'];
$verification_details = $data['verification_details'] ?? [];

// Required verification details
if ($verify && (
    !isset($verification_details['transaction_id']) || 
    !isset($verification_details['amount_verified']) || 
    !isset($verification_details['payment_status']))) {
    echo json_encode(['success' => false, 'message' => 'Missing verification details']);
    exit;
}

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Get order and payment details
    $query = "
        SELECT 
            o.*,
            ph.payment_id,
            ph.payment_status AS current_payment_status,
            ph.payment_reference
        FROM orders o
        LEFT JOIN (
            SELECT * FROM payment_history 
            WHERE order_id = ? 
            ORDER BY payment_id DESC 
            LIMIT 1
        ) ph ON o.order_id = ph.order_id
        WHERE o.order_id = ?
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $order_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found");
    }
    
    $order = $result->fetch_assoc();
    $payment_id = $order['payment_id'] ?? null;
    $payment_reference = $order['payment_reference'];
    
    // Get latest payment slip path
    $transfer_slip = null;
    $query = "
        SELECT transfer_slip FROM payment_verifications
        WHERE order_id = ?
        ORDER BY verification_attempt DESC, created_at DESC
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $slip_result = $stmt->get_result();
    if ($slip_result->num_rows > 0) {
        $slip_row = $slip_result->fetch_assoc();
        $transfer_slip = $slip_row['transfer_slip'];
    }
    
    // Get latest payment verification if exists
    $verification_id = null;
    $verification_attempt = 1;
    
    $query = "
        SELECT * FROM payment_verifications 
        WHERE order_id = ? 
        ORDER BY verification_attempt DESC, created_at DESC 
        LIMIT 1
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $verification = $result->fetch_assoc();
        $verification_id = $verification['verification_id'];
        $verification_attempt = $verification['verification_attempt'] + 1;
        
        // If the transfer slip wasn't found earlier, try to get it from this verification
        if (!$transfer_slip && !empty($verification['transfer_slip'])) {
            $transfer_slip = $verification['transfer_slip'];
        }
    }
    
    // If payment_id is still null but we have a verification, create a payment_history entry
    if (!$payment_id && $verification_id) {
        $query = "
            INSERT INTO payment_history (
                order_id, amount, payment_method_id, transaction_id, 
                payment_reference, payment_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $payment_method_id = $order['payment_method_id'];
        $amount = $order['total_amount']; 
        $transaction_id = $verification_details['transaction_id'] ?? null;
        $payment_status = $verification_details['payment_status'] ?? 0;
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param(
            "iiissi", 
            $order_id, $amount, $payment_method_id, $transaction_id, 
            $payment_reference, $payment_status
        );
        $stmt->execute();
        
        $payment_id = $mysqli->insert_id;
    }
    
    // Process verification request
    if ($verify) {
        // Extract verification details
        $transaction_id = $verification_details['transaction_id'];
        $amount_verified = (int)$verification_details['amount_verified'];
        $payment_status = (int)$verification_details['payment_status'];
        $verification_notes = $verification_details['verification_notes'] ?? '';
        $admin_id = $_SESSION['user_id'];
        
        // Create new verification record for this attempt
        $payment_verified = $payment_status === 1 ? 1 : 0; // Only mark as verified for completed status
        
        if ($payment_verified) {
            // If payment is verified, include payment_verified_at with timestamp
            $payment_verified_at = date('Y-m-d H:i:s');
            
            $query = "
                INSERT INTO payment_verifications (
                    order_id, payment_id, transaction_id, amount_verified, 
                    payment_status, verification_notes, verified_by_id, 
                    transfer_slip, payment_verified, payment_verified_at, 
                    verification_attempt
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param(
                "iisiisisiis", 
                $order_id, $payment_id, $transaction_id, $amount_verified, 
                $payment_status, $verification_notes, $admin_id, 
                $transfer_slip, $payment_verified, $payment_verified_at, $verification_attempt
            );
        } else {
            // If payment is not verified, set payment_verified_at as NULL in the query
            $query = "
                INSERT INTO payment_verifications (
                    order_id, payment_id, transaction_id, amount_verified, 
                    payment_status, verification_notes, verified_by_id, 
                    transfer_slip, payment_verified, verification_attempt,
                    payment_verified_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
            ";
            
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param(
                "iisiisisii", 
                $order_id, $payment_id, $transaction_id, $amount_verified, 
                $payment_status, $verification_notes, $admin_id, 
                $transfer_slip, $payment_verified, $verification_attempt
            );
        }
        
        $stmt->execute();
        
        $new_verification_id = $mysqli->insert_id;
        
        // Add verification log entry
        $query = "
            INSERT INTO payment_verification_logs (
                verification_id, order_id, status_changed_from, status_changed_to, 
                amount, admin_notes, verified_by_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $previous_status = isset($verification['payment_status']) ? $verification['payment_status'] : 0;
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param(
            "iiiiisi", 
            $new_verification_id, $order_id, $previous_status, $payment_status, 
            $amount_verified, $verification_notes, $admin_id
        );
        $stmt->execute();
        
        // Update or create payment history entry
        if ($payment_id) {
            // Update existing payment history
            $query = "
                UPDATE payment_history 
                SET payment_status = ?, transaction_id = ?, updated_at = NOW() 
                WHERE payment_id = ?
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("isi", $payment_status, $transaction_id, $payment_id);
            $stmt->execute();
        } else {
            // Create new payment history entry
            $query = "
                INSERT INTO payment_history (
                    order_id, amount, payment_method_id, transaction_id, 
                    payment_reference, payment_status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $payment_method_id = $order['payment_method_id'];
            $amount = $order['total_amount']; 
            
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param(
                "iiissi", 
                $order_id, $amount, $payment_method_id, $transaction_id, 
                $payment_reference, $payment_status
            );
            $stmt->execute();
            
            $payment_id = $mysqli->insert_id;
            
            // Update the payment_id in the verification record
            $query = "UPDATE payment_verifications SET payment_id = ? WHERE verification_id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("ii", $payment_id, $new_verification_id);
            $stmt->execute();
        }
        
        // Update order status based on payment verification
        if ($payment_status === 1) { // Completed
            $query = "UPDATE orders SET is_paid = 1 WHERE order_id = ?";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            
            // If order status is 'pending', update to 'confirmed'
            if ($order['status_id'] == 1) {
                $query = "UPDATE orders SET status_id = 2 WHERE order_id = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
            }
            
            // Add notification for the user
            $user_id = $order['user_id'];
            $message = "Your payment for order #{$order_id} has been verified. Your order is now being processed.";
            
            $query = "
                INSERT INTO order_notifications (order_id, user_id, message, is_read) 
                VALUES (?, ?, ?, 0)
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("iis", $order_id, $user_id, $message);
            $stmt->execute();
        } 
        else if ($payment_status === 2) { // Failed
            // Add notification asking for payment resubmission
            $user_id = $order['user_id'];
            $message = "Your payment for order #{$order_id} could not be verified. Please resubmit your payment proof.";
            $note = "Payment verification failed: " . $verification_notes;
            
            $query = "
                INSERT INTO order_notifications (order_id, user_id, message, note, is_read) 
                VALUES (?, ?, ?, ?, 0)
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("iiss", $order_id, $user_id, $message, $note);
            $stmt->execute();
            
            // Request additional proof
            $query = "
                UPDATE payment_verifications 
                SET additional_proof_requested = 1 
                WHERE verification_id = ?
            ";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("i", $new_verification_id);
            $stmt->execute();
        }
    }
    
    // Commit the transaction
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $verify ? 'Payment verified successfully' : 'Payment details retrieved',
        'verification_id' => $new_verification_id ?? null
    ]);
} 
catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing verification: ' . $e->getMessage()
    ]);
}

$mysqli->close();
?> 