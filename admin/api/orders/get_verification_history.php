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

// Validate order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$order_id = (int)$_GET['id'];

try {
    // Get verification history
    $stmt = $mysqli->prepare("
        SELECT 
            v.*,
            o.payment_verified,
            o.payment_verified_at,
            o.total_amount,
            ps.payment_method,
            COALESCE(v.payment_status, 0) as payment_status,
            ph.transaction_id AS original_transaction_id,
            u.full_name as admin_name
        FROM payment_verifications v
        JOIN orders o ON v.order_id = o.order_id
        LEFT JOIN payment_history ph ON v.payment_id = ph.payment_id
        LEFT JOIN payment_settings ps ON ph.payment_method_id = ps.id
        LEFT JOIN users u ON v.verified_by_id = u.user_id
        WHERE v.order_id = ?
        ORDER BY v.created_at DESC
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $history = [];
    
    while($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $stmt->close();
    
    // Build HTML for verification history
    $html = '';
    
    if (empty($history)) {
        $html = '<div class="alert alert-info">No verification history found for this order.</div>';
    } else {
        $html .= '<div class="list-group">';
        
        foreach ($history as $item) {
            $statusClass = $item['payment_verified'] == 1 ? 'success' : 'warning';
            $statusText = $item['payment_verified'] == 1 ? 'Verified' : 'Pending';
            $dateTime = date('F d, Y h:i A', strtotime($item['created_at']));
            
            // Get payment status badge class
            $paymentStatusText = "Not Available";
            $paymentStatusClass = "secondary";
            
            if (isset($item['payment_status'])) {
                switch($item['payment_status']) {
                    case 0:
                        $paymentStatusText = "Pending";
                        $paymentStatusClass = "warning";
                        break;
                    case 1:
                        $paymentStatusText = "Completed";
                        $paymentStatusClass = "success";
                        break;
                    case 2:
                        $paymentStatusText = "Failed";
                        $paymentStatusClass = "danger";
                        break;
                    case 3:
                        $paymentStatusText = "Refunded";
                        $paymentStatusClass = "info";
                        break;
                }
            }
            
            $html .= '
            <div class="list-group-item list-group-item-action flex-column align-items-start">
                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                    <h6 class="mb-0 text-primary">
                        <i class="bi bi-shield-check me-1"></i> Verification #' . $item['verification_id'] . '
                    </h6>
                    <small class="text-muted">' . $dateTime . '</small>
                </div>
                <p class="mb-1 d-flex justify-content-between">
                    <span><strong>Admin:</strong> ' . htmlspecialchars($item['admin_name']) . '</span>
                    <span class="badge bg-' . $statusClass . '">' . $statusText . '</span>
                </p>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Transaction ID:</strong> ' . htmlspecialchars($item['transaction_id']) . '</p>
                        <p class="mb-1"><strong>Amount Verified:</strong> $' . number_format($item['amount_verified'], 2) . '</p>';
                        
            $html .= '
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Payment Status:</strong> <span class="badge bg-' . $paymentStatusClass . '">' . $paymentStatusText . '</span></p>';
            
            if (!empty($item['payment_method'])) {
                $html .= '<p class="mb-1"><strong>Method:</strong> ' . htmlspecialchars($item['payment_method']) . '</p>';
            }
            
            // Show if there was a transaction ID update
            if (!empty($item['original_transaction_id']) && $item['original_transaction_id'] != $item['transaction_id']) {
                $html .= '<p class="mb-1 text-info"><small><i class="bi bi-info-circle"></i> Transaction ID was updated</small></p>';
            }
            
            // Show amount discrepancy warning if applicable
            if ($item['amount_verified'] != $item['total_amount']) {
                $html .= '
                <div class="alert alert-warning py-1 px-2 mb-1 small">
                    <i class="bi bi-exclamation-triangle-fill"></i> Amount mismatch: Order total was $' . number_format($item['total_amount'], 2) . '
                </div>';
            }
            
            if (!empty($item['verification_notes'])) {
                // Check if this is a resubmitted payment
                $isResubmission = strpos($item['verification_notes'], 'RESUBMITTED PAYMENT') !== false;
                $noteClass = $isResubmission ? 'bg-warning-subtle border-warning' : 'bg-light';
                
                $html .= '
                <div class="mt-2 mb-0">
                    <strong>Notes:';
                    
                // Add a badge for resubmitted payments
                if ($isResubmission) {
                    $html .= ' <span class="badge bg-warning text-dark ms-1">Resubmission</span>';
                }
                
                $html .= '</strong>
                    <p class="' . $noteClass . ' p-2 rounded small mb-0 border">' . htmlspecialchars($item['verification_notes']) . '</p>
                </div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($history)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 