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

// Helper function to get the correct meal kit image URL (copied from meal-kits.php)
function get_meal_kit_image_url($image_url_db) {
    if (!$image_url_db) return 'https://placehold.co/120x90?text=No+Image';
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    // Get the base URL up to the project root (e.g. /hm)
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0]; // e.g. '/hm'
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

// Helper function to get absolute path for any uploaded file
function get_absolute_file_path($relative_path) {
    if (!$relative_path) return '';
    if (preg_match('/^https?:\/\//i', $relative_path)) {
        return $relative_path;
    }
    
    // Get the base URL up to the project root (e.g. /hm)
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0]; // e.g. '/hm'
    
    // If the path already starts with the project base, don't add it again
    if (strpos($relative_path, $projectBase) === 0) {
        return $relative_path;
    }
    
    // If the path already contains the full path (e.g. /uploads/...), just add project base
    if (strpos($relative_path, '/uploads/') === 0) {
        return $projectBase . $relative_path;
    }
    
    // Otherwise, handle it as a path relative to project root
    return $projectBase . '/' . ltrim($relative_path, '/');
}

try {
    // Get order details
    $stmt = $mysqli->prepare("
        SELECT 
            o.*,
            os.status_name,
            u.full_name as customer_name,
            u.email as customer_email,
            ps.payment_method,
            ps.account_phone as company_account,
            ph.payment_id,
            ph.transaction_id,
            ph.amount as payment_amount,
            COALESCE(pv.payment_status, 0) as payment_status,
            COALESCE(pv.payment_verified, 0) as payment_verified,
            COALESCE(pv.verification_attempt, 0) as verification_attempt,
            pv.verification_notes,
            pv.amount_verified,
            pv.created_at as verification_date,
            (SELECT transfer_slip FROM payment_verifications WHERE order_id = o.order_id ORDER BY created_at DESC LIMIT 1) as transfer_slip
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN order_status os ON o.status_id = os.status_id
        LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
        LEFT JOIN (
            SELECT ph1.* 
            FROM payment_history ph1
            LEFT JOIN payment_history ph2 ON ph1.order_id = ph2.order_id AND ph1.payment_id < ph2.payment_id
            WHERE ph2.payment_id IS NULL
        ) ph ON o.order_id = ph.order_id
        LEFT JOIN payment_verifications pv ON ph.payment_id = pv.payment_id
        WHERE o.order_id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    if (!$order) {
        throw new Exception('Order not found');
    }
    $stmt->close();

    // Get verification details
    $verification = null;
    if ($order['payment_id']) {
        $verificationStmt = $mysqli->prepare("
            SELECT 
                pv.*,
                u.full_name as admin_name
            FROM payment_verifications pv
            LEFT JOIN users u ON pv.verified_by_id = u.user_id
            WHERE pv.payment_id = ?
            ORDER BY pv.created_at DESC
            LIMIT 1
        ");
        
        if ($verificationStmt) {
            $verificationStmt->bind_param("i", $order['payment_id']);
            $verificationStmt->execute();
            $verificationResult = $verificationStmt->get_result();
            if ($verificationResult->num_rows > 0) {
                $verification = $verificationResult->fetch_assoc();
            }
            $verificationStmt->close();
        }
    }

    // Get order items with meal kit details
    $stmt = $mysqli->prepare("
        SELECT 
            oi.*, mk.name as meal_kit_name, mk.image_url, mk.base_calories, mk.cooking_time, mk.servings
        FROM order_items oi
        JOIN meal_kits mk ON oi.meal_kit_id = mk.meal_kit_id
        WHERE oi.order_id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $items = $stmt->get_result();
    $order_items = [];
    $subtotal = 0;
    
    while ($item = $items->fetch_assoc()) {
        // Fetch ingredient customizations for this order_item
        $customs = [];
        $customStmt = $mysqli->prepare("
            SELECT oii.custom_grams, ing.name as ingredient_name
            FROM order_item_ingredients oii
            JOIN ingredients ing ON oii.ingredient_id = ing.ingredient_id
            WHERE oii.order_item_id = ?
        ");
        if ($customStmt) {
            $customStmt->bind_param("i", $item['order_item_id']);
            $customStmt->execute();
            $customResult = $customStmt->get_result();
            while ($c = $customResult->fetch_assoc()) {
                $customs[] = $c;
            }
            $customStmt->close();
        }
        $item['custom_ingredients'] = $customs;
        $order_items[] = $item;
        $subtotal += $item['price_per_unit'] * $item['quantity'];
    }

    // Normalize status name to match Bootstrap classes (lowercase, no spaces)
    $statusRaw = strtolower(trim($order['status_name'] ?? $order['status'] ?? ''));
    $statusMap = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
    ];
    $statusClass = $statusMap[$statusRaw] ?? 'secondary';

    // Get payment status display name and Bootstrap class
    $paymentStatusText = "Not Available";
    $paymentStatusClass = "secondary";
    
    if (isset($order['payment_status'])) {
        switch($order['payment_status']) {
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

    // Build HTML for order details
    $html = '
    <div class="order-details">
        <div class="row mb-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="p-3 rounded-4 shadow-sm h-100" style="background:linear-gradient(90deg,#e3f2fd 60%,#bbdefb 100%); border-left:6px solid #1976d2;">
                    <h6 class="fw-bold mb-2" style="color:#1976d2;"><i class="bi bi-receipt"></i> Order Information</h6>
                    <p class="mb-1"><strong>Order ID:</strong> <span style="color:#1976d2;">#' . $order['order_id'] . '</span></p>
                    <p class="mb-1"><strong>Date:</strong> <span style="color:#0288d1;">' . date('F d, Y h:i A', strtotime($order['created_at'])) . '</span></p>
                    <p class="mb-1">
                        <strong>Status:</strong> 
                        <span class="badge bg-' . $statusClass . ' fw-bold px-3 py-2 rounded-pill" style="font-size:1em;min-width:110px;letter-spacing:0.5px;">' . htmlspecialchars($order['status_name'] ?? $order['status'] ?? '') . '</span>
                    </p>
                    <p class="mb-1"><strong>Payment Method:</strong> <span style="color:#388e3c;">' . htmlspecialchars($order['payment_method']) . '</span></p>';
                    
    // Add payment status information
    $html .= '<p class="mb-1">
                <strong>Payment Status:</strong> 
                <span class="badge bg-' . $paymentStatusClass . ' fw-bold px-3 py-2 rounded-pill" style="font-size:1em;min-width:110px;letter-spacing:0.5px;">' . $paymentStatusText . '</span>
            </p>';
                
    // Add transaction ID if available
    if (!empty($order['transaction_id'])) {
        $html .= '<p class="mb-1"><strong>Transaction ID:</strong> <span style="color:#d81b60;">' . htmlspecialchars($order['transaction_id']) . '</span></p>';
    }
                
    $html .= '</div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-4 shadow-sm h-100" style="background:linear-gradient(90deg,#fffde7 60%,#ffe082 100%); border-left:6px solid #fbc02d;">
                    <h6 class="fw-bold mb-2" style="color:#fbc02d;"><i class="bi bi-person-circle"></i> Customer Information</h6>
                    <p class="mb-1"><strong>Name:</strong> <span style="color:#7b1fa2;">' . htmlspecialchars($order['customer_name']) . '</span></p>
                    <p class="mb-1"><strong>Email:</strong> <span style="color:#0288d1;">' . htmlspecialchars($order['customer_email']) . '</span></p>
                    <p class="mb-1"><strong>Primary Contact:</strong> <span style="color:#388e3c;">' . htmlspecialchars($order['customer_phone']) . '</span></p>
                    <p class="mb-1"><strong>Alternate Contact:</strong> <span style="color:#388e3c;">' . htmlspecialchars($order['contact_number']) . '</span></p>
                    <p class="mb-1"><strong>Delivery Address:</strong> <span style="color:#6a1b9a;">' . htmlspecialchars($order['delivery_address']) . '</span></p>
                    <p class="mb-1"><strong>Delivery Notes:</strong> <span style="color:#ef6c00;">' . htmlspecialchars($order['delivery_notes'] ?? 'None') . '</span></p>
                </div>
            </div>
        </div>';
        
    // Add payment verification section if there's a transfer slip
    if (!empty($order['transfer_slip'])) {
        $slipUrl = get_absolute_file_path($order['transfer_slip']); // Use helper function for correct path
        $verifyBtnClass = $order['payment_verified'] == 1 ? 'btn-success disabled' : 'btn-primary';
        $verifyBtnText = $order['payment_verified'] == 1 ? 'Payment Verified' : 'Verify Payment';
        $verifiedAtText = $order['payment_verified'] == 1 && !empty($order['verification_date']) 
            ? '<div class="text-success"><i class="bi bi-check-circle-fill"></i> Verified on ' . date('F d, Y h:i A', strtotime($order['verification_date'])) . '</div>' 
            : '';
        
        // Get verification details if available
        $verificationDetails = null;
        $verificationStmt = $mysqli->prepare("
            SELECT v.*, u.full_name as admin_name 
            FROM payment_verifications v
            LEFT JOIN users u ON v.verified_by_id = u.user_id
            WHERE v.order_id = ? 
            ORDER BY v.created_at DESC 
            LIMIT 1
        ");
        
        if ($verificationStmt) {
            $verificationStmt->bind_param("i", $order_id);
            $verificationStmt->execute();
            $verificationResult = $verificationStmt->get_result();
            if ($verificationResult->num_rows > 0) {
                $verificationDetails = $verificationResult->fetch_assoc();
            }
            $verificationStmt->close();
        }
        
        $html .= '
        <div class="row mb-4">
            <div class="col-12">
                <div class="p-3 rounded-4 shadow-sm" style="background:linear-gradient(90deg,#e8f5e9 60%,#c8e6c9 100%); border-left:6px solid #4caf50;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold" style="color:#2e7d32;"><i class="bi bi-credit-card-2-back"></i> Payment Verification</h6>
                        ' . $verifiedAtText . '
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Payment Slip:</strong></p>
                            <a href="' . htmlspecialchars($slipUrl) . '" target="_blank" class="payment-slip-container">
                                <img src="' . htmlspecialchars($slipUrl) . '" alt="Payment Slip" class="img-fluid rounded shadow-sm mb-2" style="max-height: 200px; border: 2px solid #c8e6c9;">
                                <div class="zoom-overlay"><i class="bi bi-zoom-in"></i> Click to enlarge</div>
                            </a>
                            <div class="mt-3">
                                <strong>Customer Provided Info:</strong>
                                <div class="p-2 rounded bg-white mt-1">
                                    <p class="mb-1"><i class="bi bi-calendar3"></i> <strong>Date:</strong> ' . date('F d, Y h:i A', strtotime($order['created_at'])) . '</p>
                                    <p class="mb-1"><i class="bi bi-cash-stack"></i> <strong>Amount:</strong> <span class="text-danger">$' . number_format($order['total_amount'], 2) . '</span></p>';
        
        if (!empty($order['company_account'])) {
            $html .= '<p class="mb-1"><i class="bi bi-phone"></i> <strong>Account:</strong> ' . htmlspecialchars($order['company_account']) . '</p>';
        }
        
        $html .= '
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex flex-column">';
        
        // Show verification form or verification details
        if ($order['payment_verified'] == 1 && $verificationDetails) {
            // Show verification details
            $html .= '
                <div class="bg-white p-3 rounded mb-3 shadow-sm">
                    <h6 class="fw-bold text-success mb-3"><i class="bi bi-shield-check"></i> Verification Details</h6>
                    <p class="mb-1"><strong>Admin:</strong> ' . htmlspecialchars($verificationDetails['admin_name']) . '</p>
                    <p class="mb-1"><strong>Transaction ID:</strong> <span class="text-primary">' . htmlspecialchars($verificationDetails['transaction_id']) . '</span></p>
                    <p class="mb-1"><strong>Amount Verified:</strong> <span class="text-success">$' . number_format($verificationDetails['amount_verified'], 2) . '</span></p>';
            
            if (!empty($verificationDetails['verification_notes'])) {
                $html .= '
                    <div class="mt-2">
                        <strong>Notes:</strong>
                        <p class="bg-light p-2 rounded small mb-0">' . htmlspecialchars($verificationDetails['verification_notes']) . '</p>
                    </div>';
            }
            
            $html .= '
                </div>';
            
            if ($verificationDetails['amount_verified'] != $order['total_amount']) {
                $html .= '
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Amount Discrepancy:</strong> 
                    The verified amount ($' . number_format($verificationDetails['amount_verified'], 2) . ') 
                    does not match the order total ($' . number_format($order['total_amount'], 2) . ').
                </div>';
            }
            
            // Show history button only (remove the revoke button)
            $html .= '
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-info flex-grow-1" 
                            onclick="showPaymentHistory(' . $order['order_id'] . ')">
                        <i class="bi bi-clock-history me-1"></i> History
                    </button>
                </div>';
        } else {
            // Remove verification form and just show payment information and history button
            $html .= '
                <div class="bg-white p-3 rounded mb-3 shadow-sm">
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-clipboard-check"></i> Payment Information</h6>
                    <p class="mb-1"><strong>Total Amount:</strong> <span class="text-success">$' . number_format($order['total_amount'], 2) . '</span></p>';
                    
            if (!empty($order['company_account'])) {
                $html .= '<p class="mb-1"><strong>Account:</strong> ' . htmlspecialchars($order['company_account']) . '</p>';
            }
            
            $html .= '
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle-fill"></i> To verify payment, please use the "Verify" button from the main orders list.
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-outline-info flex-grow-1" 
                                onclick="showPaymentHistory(' . $order['order_id'] . ')">
                            <i class="bi bi-clock-history me-1"></i> Payment History
                        </button>
                    </div>
                </div>';
        }
        
        $html .= '
                            <div class="mt-auto">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-light px-3 py-2 rounded">
                                        <strong>Payment Status:</strong>
                                        <span class="badge bg-' . $paymentStatusClass . ' px-3 py-2 ms-2">' . $paymentStatusText . '</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    } else {
        // Show payment info even if no transfer slip
        $html .= '
        <div class="row mb-4">
            <div class="col-12">
                <div class="p-3 rounded-4 shadow-sm" style="background:linear-gradient(90deg,#e3f2fd 60%,#bbdefb 100%); border-left:6px solid #1976d2;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold" style="color:#1976d2;"><i class="bi bi-credit-card-2-back"></i> Payment Information</h6>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <strong>Payment Status:</strong>
                                <span class="badge bg-' . $paymentStatusClass . ' px-3 py-2">' . $paymentStatusText . '</span>
                            </div>
                            <p class="mb-1"><strong>Payment Method:</strong> <span style="color:#388e3c;">' . htmlspecialchars($order['payment_method']) . '</span></p>';
        
        if (!empty($order['transaction_id'])) {
            $html .= '<p class="mb-1"><strong>Transaction ID:</strong> <span style="color:#1976d2;">' . htmlspecialchars($order['transaction_id']) . '</span></p>';
        }
                            
        $html .= '      <p class="mb-0"><strong>Amount:</strong> <span style="color:#d81b60; font-weight: bold; font-size: 1.2em;">$' . number_format($order['total_amount'], 2) . '</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    $html .= '<div class="order-details-list">';
    foreach ($order_items as $item) {
        $img_url = get_meal_kit_image_url($item['image_url']);
        $html .= '
        <div class="order-item-box mb-3 p-3 rounded-4 shadow-sm" style="background:linear-gradient(90deg,#f3e5f5 60%,#e1bee7 100%); border:2.5px solid #7b1fa2;">
            <div class="row g-2 align-items-center flex-wrap flex-md-nowrap">
                <div class="col-4 col-sm-3 col-md-2 text-center">
                    <img src="' . htmlspecialchars($img_url) . '" alt="' . htmlspecialchars($item['meal_kit_name']) . '" class="img-thumbnail shadow-sm" style="width:100%;max-width:90px;max-height:70px;object-fit:cover;">
                </div>
                <div class="col-8 col-sm-9 col-md-10">
                    <div class="fw-bold mb-1" style="font-size:1.15em;color:#6a1b9a;text-shadow:0 1px 6px #ce93d8;">' . htmlspecialchars($item['meal_kit_name']) . '</div>';
        if (!empty($item['customization_notes'])) {
            $html .= '<div class="customization-note-box mb-2 p-2 rounded-3 d-inline-block w-100" style="background:linear-gradient(90deg,#fffde7 60%,#ffe082 100%); border-left:6px solid #fbc02d; box-shadow:0 2px 8px #ffe082; font-size:1em;">
                <span class="fw-bold text-warning" style="font-size:1.05em;"><i class="bi bi-pencil-square"></i> Note:</span> <span class="text-dark">' . htmlspecialchars($item['customization_notes']) . '</span>
            </div>';
        }
        if (!empty($item['custom_ingredients'])) {
            $html .= '<div class="custom-ingredients-box mt-1 mb-1 p-2 rounded-3" style="background: linear-gradient(90deg,#e3f2fd 60%,#bbdefb 100%); border: 2.5px solid #1976d2; box-shadow: 0 4px 16px 0 rgba(33,150,243,0.13);">
                <div class="mb-1 fw-bold" style="color:#1976d2; font-size:1.05em; letter-spacing:0.5px;"><i class="bi bi-sliders"></i> <span style="text-shadow:0 1px 8px #90caf9;">Customized Ingredients</span></div>';
            $html .= '<ul class="mb-0 ps-3" style="list-style:square inside;">';
            foreach ($item['custom_ingredients'] as $ci) {
                $html .= '<li style="margin-bottom:2px;"><span style="color:#0d47a1;font-weight:600;font-size:1em;">' . htmlspecialchars($ci['ingredient_name']) . '</span>: <span style="color:#388e3c;font-weight:700;font-size:1em;">' . htmlspecialchars($ci['custom_grams']) . 'g</span></li>';
            }
            $html .= '</ul></div>';
        }
        // Standout details
        $html .= '<div class="row mt-2 g-2">
            <div class="col-6 col-md-3">
                <div style="background:#fff3e0;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #ffcc80;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#ef6c00;font-size:1.05em;"><i class="bi bi-fire"></i> ' . $item['base_calories'] . ' kcal</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div style="background:#e8f5e9;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #a5d6a7;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#388e3c;font-size:1.05em;"><i class="bi bi-clock"></i> ' . $item['cooking_time'] . ' min</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div style="background:#e3f2fd;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #90caf9;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#1976d2;font-size:1.05em;"><i class="bi bi-people"></i> ' . $item['servings'] . '</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div style="background:#f3e5f5;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #ce93d8;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#7b1fa2;font-size:1.05em;"><i class="bi bi-basket"></i> Qty: ' . $item['quantity'] . '</span>
                </div>
            </div>
        </div>';
        $html .= '<div class="row mt-2 g-2">
            <div class="col-6">
                <div style="background:#fffde7;border-radius:1em;padding:0.4em 0.8em;box-shadow:0 2px 8px #fff9c4;display:inline-block;min-width:90px;">
                    <span class="text-muted">Unit:</span> <span class="fw-bold" style="color:#fbc02d;">$' . number_format($item['price_per_unit'], 2) . '</span>
                </div>
            </div>
            <div class="col-6 text-end">
                <div style="background:#e1f5fe;border-radius:1em;padding:0.4em 0.8em;box-shadow:0 2px 8px #b3e5fc;display:inline-block;min-width:90px;">
                    <span class="text-muted">Total:</span> <span class="fw-bold" style="color:#0288d1;">$' . number_format($item['price_per_unit'] * $item['quantity'], 2) . '</span>
                </div>
            </div>
        </div>';
        $html .= '</div></div></div>';
    }
    $html .= '</div>';
    // Order summary
    $html .= '<div class="order-summary-box mt-4 p-3 rounded-4 shadow-sm" style="background:linear-gradient(90deg,#fffde7 60%,#ffe082 100%); border:2.5px solid #fbc02d;">
        <div class="row g-2">
            <div class="col-12 col-sm-6 col-md-3">
                <span class="fw-bold text-muted">Subtotal:</span> <span class="fw-bold" style="color:#ef6c00;">$' . number_format($subtotal, 2) . '</span>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <span class="fw-bold text-muted">Tax:</span> <span class="fw-bold" style="color:#d81b60;">$' . number_format($order['tax'], 2) . '</span>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <span class="fw-bold text-muted">Delivery Fee:</span> <span class="fw-bold" style="color:#0288d1;">$' . number_format($order['delivery_fee'], 2) . '</span>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <span class="fw-bold text-muted">Total:</span> <span class="fw-bold" style="color:#7b1fa2;font-size:1.1em;">$' . number_format($order['total_amount'], 2) . '</span>
            </div>
        </div>
    </div>';

    // Prepare response data
    $response = [
        'success' => true,
        'html' => $html,
        'order' => [
            'order_id' => $order['order_id'],
            'total_amount' => number_format($order['total_amount'], 2, '.', ''),
            'account_phone' => $order['company_account'] ?? '',
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'payment_verified' => $order['payment_verified']
        ],
        'payment_setting' => [
            'account_phone' => $order['company_account']
        ],
        'verification' => $verification,
        'payment_slip' => $order['transfer_slip'] ? get_absolute_file_path($order['transfer_slip']) : null
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 