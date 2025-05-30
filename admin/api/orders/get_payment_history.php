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

// Validate required parameter
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$order_id = (int)$_GET['order_id'];

try {
    // Get payment verification history
    $verifications_stmt = $mysqli->prepare("
        SELECT 
            pv.verification_id,
            pv.payment_id,
            pv.order_id,
            pv.verified_by_id,
            u.full_name as verified_by_name,
            pv.payment_status,
            pv.payment_verified,
            pv.payment_verified_at,
            pv.verification_notes,
            pv.transaction_id,
            pv.amount_verified as amount,
            pv.verification_attempt,
            pv.resubmission_status,
            pv.created_at,
            pv.updated_at
        FROM payment_verifications pv
        LEFT JOIN users u ON pv.verified_by_id = u.user_id
        WHERE pv.order_id = ?
        ORDER BY pv.created_at DESC
    ");
    
    if (!$verifications_stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }
    
    $verifications_stmt->bind_param("i", $order_id);
    $verifications_stmt->execute();
    $verifications_result = $verifications_stmt->get_result();
    
    $verifications = [];
    while ($row = $verifications_result->fetch_assoc()) {
        $verifications[] = $row;
    }
    
    // If no verifications found, include payment history record instead
    if (empty($verifications)) {
        $payment_stmt = $mysqli->prepare("
            SELECT 
                ph.payment_id,
                ph.order_id,
                ph.amount,
                ph.payment_method_id,
                ps.payment_method,
                ph.transaction_id,
                ph.payment_reference,
                ph.payment_status,
                ph.created_at,
                ph.updated_at
            FROM payment_history ph
            LEFT JOIN payment_settings ps ON ph.payment_method_id = ps.id
            WHERE ph.order_id = ?
            ORDER BY ph.created_at DESC
        ");
        
        $payment_stmt->bind_param("i", $order_id);
        $payment_stmt->execute();
        $payment_result = $payment_stmt->get_result();
        
        while ($row = $payment_result->fetch_assoc()) {
            $verifications[] = [
                'payment_id' => $row['payment_id'],
                'order_id' => $row['order_id'],
                'payment_status' => $row['payment_status'],
                'transaction_id' => $row['transaction_id'],
                'amount' => $row['amount'],
                'payment_method' => $row['payment_method'],
                'verification_notes' => null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        $payment_stmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'history' => $verifications
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching payment history: ' . $e->getMessage()
    ]);
}

// Close database connection
$mysqli->close(); 