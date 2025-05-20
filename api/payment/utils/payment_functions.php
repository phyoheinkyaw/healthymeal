<?php
/**
 * Payment utility functions
 * Used to centralize payment-related functionality
 */

/**
 * Get payment status text based on status code
 * 
 * @param int $status_code The payment status code
 * @return string The human-readable status text
 */
function getPaymentStatusText($status_code) {
    switch ((int)$status_code) {
        case 0:
            return 'Pending';
        case 1:
            return 'Completed';
        case 2:
            return 'Failed';
        case 3:
            return 'Refunded';
        case 4:
            return 'Partial Payment';
        default:
            return 'Unknown';
    }
}

/**
 * Get payment status class (for Bootstrap styling)
 * 
 * @param int $status_code The payment status code
 * @return string The Bootstrap class for this status
 */
function getPaymentStatusClass($status_code) {
    switch ((int)$status_code) {
        case 0:
            return 'warning';
        case 1:
            return 'success';
        case 2:
            return 'danger';
        case 3:
            return 'info';
        case 4:
            return 'warning'; // Partial payment uses warning too
        default:
            return 'secondary';
    }
}

/**
 * Get the latest payment verification record for an order
 * 
 * @param mysqli $mysqli Database connection
 * @param int $order_id Order ID to check
 * @return array|null The verification record or null if not found
 */
function getLatestPaymentVerification($mysqli, $order_id) {
    $stmt = $mysqli->prepare("
        SELECT pv.*, ph.payment_method_id
        FROM payment_verifications pv
        JOIN payment_history ph ON pv.payment_id = ph.payment_id
        WHERE pv.order_id = ?
        ORDER BY pv.created_at DESC
        LIMIT 1
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $verification = $result->fetch_assoc();
        $stmt->close();
        return $verification;
    }
    
    return null;
}

/**
 * Get the count of payment verification attempts for an order
 * 
 * @param mysqli $mysqli Database connection
 * @param int $order_id Order ID to check
 * @return int The number of verification attempts
 */
function getVerificationAttemptCount($mysqli, $order_id) {
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM payment_verifications 
        WHERE order_id = ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return (int)($data['attempt_count'] ?? 0);
    }
    
    return 0;
}

/**
 * Check if payment verification is still needed
 * 
 * @param mysqli $mysqli Database connection
 * @param int $order_id Order ID to check
 * @return bool True if payment verification is needed, false otherwise 
 */
function needsPaymentVerification($mysqli, $order_id) {
    // Get the latest verification
    $verification = getLatestPaymentVerification($mysqli, $order_id);
    
    // If no verification exists or status is not completed (1)
    if (!$verification || (int)$verification['payment_status'] !== 1) {
        // Check if order has a payment method that requires verification
        $stmt = $mysqli->prepare("
            SELECT o.payment_method_id, ps.payment_method 
            FROM orders o
            JOIN payment_settings ps ON o.payment_method_id = ps.id
            WHERE o.order_id = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();
            
            // Cash on Delivery doesn't need verification
            if ($payment && $payment['payment_method'] === 'Cash on Delivery') {
                return false;
            }
            
            return true;
        }
    }
    
    return false;
}

/**
 * Create a notification for payment verification
 * 
 * @param mysqli $mysqli Database connection
 * @param int $order_id Order ID
 * @param int $user_id User ID to notify
 * @param string $message Notification message
 * @param string $note Additional notes (optional)
 * @return bool True if successful, false otherwise
 */
function createPaymentNotification($mysqli, $order_id, $user_id, $message, $note = '') {
    $stmt = $mysqli->prepare("
        INSERT INTO order_notifications (
            order_id, user_id, message, note, is_read
        ) VALUES (?, ?, ?, ?, 0)
    ");
    
    if ($stmt) {
        $stmt->bind_param("iiss", $order_id, $user_id, $message, $note);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

/**
 * Get the available payment methods
 * 
 * @param mysqli $mysqli Database connection 
 * @return array The list of payment methods
 */
function getPaymentMethods($mysqli) {
    // ... existing code ...
} 