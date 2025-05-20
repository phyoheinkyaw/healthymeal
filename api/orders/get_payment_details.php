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
    // Get order details with payment information
    $query = "
        SELECT 
            o.*,
            ph.payment_id,
            ph.transaction_id as existing_transaction_id,
            ph.payment_status,
            pm.payment_method,
            pm.account_phone as payment_account,
            (SELECT transfer_slip FROM payment_verifications 
             WHERE order_id = o.order_id 
             ORDER BY verification_attempt DESC, created_at DESC 
             LIMIT 1) as transfer_slip
        FROM orders o
        LEFT JOIN (
            SELECT * FROM payment_history 
            WHERE order_id = ? 
            ORDER BY payment_id DESC 
            LIMIT 1
        ) ph ON o.order_id = ph.order_id
        LEFT JOIN payment_settings pm ON o.payment_method_id = pm.id
        WHERE o.order_id = ?
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $order_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $result->fetch_assoc();
    
    // Get customer details
    $query = "SELECT full_name, email FROM users WHERE user_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $order['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    
    // Format payment details
    $paymentDetails = [
        'order_id' => $order['order_id'],
        'payment_id' => $order['payment_id'],
        'order_amount' => $order['total_amount'],
        'payment_method' => $order['payment_method'],
        'payment_status' => $order['payment_status'] ?? 0,
        'payment_status_text' => getPaymentStatusText($order['payment_status'] ?? 0),
        'transaction_id' => $order['existing_transaction_id'],
        'company_account' => $order['payment_account'],
        'customer_name' => $customer['full_name'],
        'customer_email' => $customer['email'],
        'created_at' => $order['created_at'],
        'created_at_formatted' => date('M d, Y h:i A', strtotime($order['created_at'])),
        'transfer_slip' => $order['transfer_slip'],
        'transfer_slip_url' => $order['transfer_slip'] ? '../' . $order['transfer_slip'] : null
    ];
    
    // Get verification history
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
    
    $verificationHistory = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for better readability
        $row['created_at_formatted'] = date('M d, Y h:i A', strtotime($row['created_at']));
        if ($row['payment_verified_at']) {
            $row['payment_verified_at_formatted'] = date('M d, Y h:i A', strtotime($row['payment_verified_at']));
        }
        
        // Add payment status text
        $row['payment_status_text'] = getPaymentStatusText($row['payment_status']);
        
        // Add transfer slip URL if available
        if (!empty($row['transfer_slip'])) {
            $row['transfer_slip_url'] = '../' . $row['transfer_slip'];
        }
        
        $verificationHistory[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'payment_details' => $paymentDetails,
        'verification_history' => $verificationHistory
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving payment details: ' . $e->getMessage()
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