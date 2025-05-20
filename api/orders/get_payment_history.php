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

// Get order ID from request
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // First get basic order information
    $query = "
        SELECT 
            o.*,
            u.full_name as customer_name,
            u.email as customer_email,
            pm.payment_method,
            pm.account_phone as payment_account
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN payment_settings pm ON o.payment_method_id = pm.id
        WHERE o.order_id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    // Get payment history combined with verification data
    $query = "
        SELECT 
            ph.payment_id,
            ph.transaction_id,
            ph.payment_reference,
            ph.payment_status,
            ph.created_at as payment_created_at,
            ph.updated_at as payment_updated_at,
            pv.verification_id,
            pv.transaction_id as verification_transaction_id,
            pv.amount_verified,
            pv.payment_status as verification_status,
            pv.verification_notes,
            pv.transfer_slip,
            pv.payment_verified,
            pv.payment_verified_at,
            pv.additional_proof_requested,
            pv.verification_attempt,
            pv.created_at as verification_created_at,
            u.full_name as verified_by_name
        FROM payment_history ph
        LEFT JOIN payment_verifications pv ON ph.order_id = pv.order_id AND ph.payment_id = pv.payment_id
        LEFT JOIN users u ON pv.verified_by_id = u.user_id
        WHERE ph.order_id = ?
        ORDER BY ph.payment_id DESC, pv.verification_attempt DESC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $paymentHistory = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates
        $row['payment_created_at_formatted'] = date('M d, Y h:i A', strtotime($row['payment_created_at']));
        if ($row['payment_updated_at']) {
            $row['payment_updated_at_formatted'] = date('M d, Y h:i A', strtotime($row['payment_updated_at']));
        }
        
        if ($row['verification_created_at']) {
            $row['verification_created_at_formatted'] = date('M d, Y h:i A', strtotime($row['verification_created_at']));
        }
        
        if ($row['payment_verified_at']) {
            $row['payment_verified_at_formatted'] = date('M d, Y h:i A', strtotime($row['payment_verified_at']));
        }
        
        // Add payment status text
        $row['payment_status_text'] = getPaymentStatusText($row['payment_status']);
        if (isset($row['verification_status'])) {
            $row['verification_status_text'] = getPaymentStatusText($row['verification_status']);
        }
        
        // Add transfer slip URL if available
        if (!empty($row['transfer_slip'])) {
            $row['transfer_slip_url'] = '../' . $row['transfer_slip'];
        }
        
        $paymentHistory[] = $row;
    }
    
    // If no payment history was found but we have verification data
    // This can happen if the payment_history entry is missing but verification records exist
    if (empty($paymentHistory)) {
        $query = "
            SELECT 
                pv.*,
                u.full_name as verified_by_name
            FROM payment_verifications pv
            LEFT JOIN users u ON pv.verified_by_id = u.user_id
            WHERE pv.order_id = ?
            ORDER BY pv.verification_attempt DESC, pv.created_at DESC
        ";
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Format dates
            $row['verification_created_at_formatted'] = date('M d, Y h:i A', strtotime($row['created_at']));
            if ($row['payment_verified_at']) {
                $row['payment_verified_at_formatted'] = date('M d, Y h:i A', strtotime($row['payment_verified_at']));
            }
            
            // Add payment status text
            $row['verification_status_text'] = getPaymentStatusText($row['payment_status']);
            
            // Add transfer slip URL if available
            if (!empty($row['transfer_slip'])) {
                $row['transfer_slip_url'] = '../' . $row['transfer_slip'];
            }
            
            $paymentHistory[] = $row;
        }
    }
    
    // Get verification logs for detailed history
    $query = "
        SELECT 
            pvl.*,
            u.full_name as verified_by_name
        FROM payment_verification_logs pvl
        JOIN users u ON pvl.verified_by_id = u.user_id
        WHERE pvl.order_id = ?
        ORDER BY pvl.created_at DESC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $verificationLogs = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates
        $row['created_at_formatted'] = date('M d, Y h:i A', strtotime($row['created_at']));
        
        // Add status text
        $row['status_from_text'] = getPaymentStatusText($row['status_changed_from']);
        $row['status_to_text'] = getPaymentStatusText($row['status_changed_to']);
        
        $verificationLogs[] = $row;
    }
    
    // Combine and return all data
    echo json_encode([
        'success' => true,
        'order' => [
            'order_id' => $order['order_id'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'payment_method' => $order['payment_method'],
            'payment_account' => $order['payment_account'],
            'total_amount' => $order['total_amount'],
            'created_at' => $order['created_at'],
            'created_at_formatted' => date('M d, Y h:i A', strtotime($order['created_at']))
        ],
        'payment_history' => $paymentHistory,
        'verification_logs' => $verificationLogs
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving payment history: ' . $e->getMessage()
    ]);
}

// Helper function to get payment status text
function getPaymentStatusText($status) {
    switch ($status) {
        case 0:
            return 'Pending';
        case 1:
            return 'Completed';
        case 2:
            return 'Failed';
        case 3:
            return 'Refunded';
        case 4:
            return 'Partial';
        default:
            return 'Unknown';
    }
}

$mysqli->close();
?> 