<?php
/**
 * Payment records synchronization utility
 * Used to ensure payment history and verification records are in sync
 */

/**
 * Synchronize payment history and verification records for an order
 * 
 * @param mysqli $mysqli Database connection
 * @param int $order_id The order ID to synchronize
 * @return array Result of synchronization operation
 */
function synchronizePaymentRecords($mysqli, $order_id) {
    $result = [
        'success' => false,
        'message' => '',
        'fixed_issues' => []
    ];
    
    // Check if order exists
    $orderStmt = $mysqli->prepare("SELECT order_id, payment_method_id, total_amount FROM orders WHERE order_id = ?");
    $orderStmt->bind_param("i", $order_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        $result['message'] = 'Order not found';
        return $result;
    }
    
    $order = $orderResult->fetch_assoc();
    
    // Get payment history records
    $paymentHistoryStmt = $mysqli->prepare("
        SELECT payment_id, amount, payment_status
        FROM payment_history 
        WHERE order_id = ?
        ORDER BY created_at DESC
    ");
    $paymentHistoryStmt->bind_param("i", $order_id);
    $paymentHistoryStmt->execute();
    $paymentHistory = $paymentHistoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get verification records
    $verificationStmt = $mysqli->prepare("
        SELECT verification_id, payment_id, payment_status, amount_verified
        FROM payment_verifications 
        WHERE order_id = ?
        ORDER BY created_at DESC
    ");
    $verificationStmt->bind_param("i", $order_id);
    $verificationStmt->execute();
    $verifications = $verificationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Check if there are no payment records
    if (empty($paymentHistory)) {
        $result['message'] = 'No payment records found for this order';
        return $result;
    }
    
    // Get latest payment record
    $latestPayment = $paymentHistory[0];
    
    // Issue 1: Check if payment history amounts match order total
    foreach ($paymentHistory as $payment) {
        if ($payment['amount'] != $order['total_amount']) {
            // Update payment amount to match order total
            $updatePaymentStmt = $mysqli->prepare("
                UPDATE payment_history 
                SET amount = ? 
                WHERE payment_id = ?
            ");
            $updatePaymentStmt->bind_param("ii", $order['total_amount'], $payment['payment_id']);
            $updatePaymentStmt->execute();
            
            $result['fixed_issues'][] = "Updated payment ID {$payment['payment_id']} amount to match order total";
        }
    }
    
    // Issue 2: Check for verification records without payment IDs
    $orphanedVerifications = [];
    foreach ($verifications as $verification) {
        $hasMatchingPayment = false;
        foreach ($paymentHistory as $payment) {
            if ($verification['payment_id'] == $payment['payment_id']) {
                $hasMatchingPayment = true;
                break;
            }
        }
        
        if (!$hasMatchingPayment) {
            $orphanedVerifications[] = $verification;
        }
    }
    
    // Fix orphaned verifications by linking them to the latest payment
    if (!empty($orphanedVerifications)) {
        foreach ($orphanedVerifications as $verification) {
            $updateVerificationStmt = $mysqli->prepare("
                UPDATE payment_verifications 
                SET payment_id = ? 
                WHERE verification_id = ?
            ");
            $updateVerificationStmt->bind_param("ii", $latestPayment['payment_id'], $verification['verification_id']);
            $updateVerificationStmt->execute();
            
            $result['fixed_issues'][] = "Linked orphaned verification ID {$verification['verification_id']} to payment ID {$latestPayment['payment_id']}";
        }
    }
    
    // Issue 3: Check for inconsistent verification amounts
    foreach ($verifications as $verification) {
        if ($verification['amount_verified'] > 0 && $verification['amount_verified'] != $order['total_amount']) {
            $updateVerificationStmt = $mysqli->prepare("
                UPDATE payment_verifications 
                SET amount_verified = ? 
                WHERE verification_id = ?
            ");
            $updateVerificationStmt->bind_param("ii", $order['total_amount'], $verification['verification_id']);
            $updateVerificationStmt->execute();
            
            $result['fixed_issues'][] = "Updated verification ID {$verification['verification_id']} amount to match order total";
        }
    }
    
    // Issue 4: Check for payments without verifications
    foreach ($paymentHistory as $payment) {
        $hasVerification = false;
        foreach ($verifications as $verification) {
            if ($verification['payment_id'] == $payment['payment_id']) {
                $hasVerification = true;
                break;
            }
        }
        
        if (!$hasVerification) {
            // Create a verification record for this payment
            $verificationStmt = $mysqli->prepare("
                INSERT INTO payment_verifications (
                    order_id, payment_id, amount_verified, 
                    payment_status, verification_notes, verified_by_id
                ) VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $notes = "Auto-created during payment record synchronization";
            
            $verificationStmt->bind_param(
                "iiiss",
                $order_id,
                $payment['payment_id'],
                $order['total_amount'],
                $payment['payment_status'],
                $notes
            );
            $verificationStmt->execute();
            
            $result['fixed_issues'][] = "Created verification record for payment ID {$payment['payment_id']}";
        }
    }
    
    // If we fixed any issues, it was successful
    if (count($result['fixed_issues']) > 0) {
        $result['success'] = true;
        $result['message'] = 'Payment records synchronized successfully. Fixed ' . count($result['fixed_issues']) . ' issues.';
    } else {
        $result['success'] = true;
        $result['message'] = 'Payment records are already in sync.';
    }
    
    return $result;
}

/**
 * Check if an order has inconsistent payment records
 * 
 * @param mysqli $mysqli Database connection
 * @param int $order_id The order ID to check
 * @return bool True if inconsistencies exist
 */
function hasPaymentInconsistencies($mysqli, $order_id) {
    // Get order details
    $orderStmt = $mysqli->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
    $orderStmt->bind_param("i", $order_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        return false; // Order not found
    }
    
    $order = $orderResult->fetch_assoc();
    
    // Check payment history records
    $stmt = $mysqli->prepare("
        SELECT ph.payment_id, ph.amount,
              (SELECT COUNT(*) FROM payment_verifications WHERE payment_id = ph.payment_id) as verification_count
        FROM payment_history ph
        WHERE ph.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($payment = $result->fetch_assoc()) {
        // Check if amount doesn't match order total
        if ($payment['amount'] != $order['total_amount']) {
            return true;
        }
        
        // Check if payment has no verifications
        if ($payment['verification_count'] == 0) {
            return true;
        }
    }
    
    // Check verification records
    $stmt = $mysqli->prepare("
        SELECT pv.verification_id, pv.payment_id, pv.amount_verified
        FROM payment_verifications pv
        WHERE pv.order_id = ?
        AND (
            pv.amount_verified != ? AND pv.amount_verified > 0
            OR NOT EXISTS (SELECT 1 FROM payment_history ph WHERE ph.payment_id = pv.payment_id)
        )
    ");
    $stmt->bind_param("ii", $order_id, $order['total_amount']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return true; // Found inconsistencies
    }
    
    return false; // No inconsistencies found
} 