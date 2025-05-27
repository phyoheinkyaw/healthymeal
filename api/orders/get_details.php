<?php
session_start();
require_once '../../config/connection.php';

// Load payment utility functions
require_once '../../api/payment/utils/payment_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get order ID
$order_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Check if the order belongs to the user
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
        COALESCE(latest_pv.payment_status, 0) as payment_status,
        COALESCE(latest_pv.payment_verified, 0) as payment_verified,
        COALESCE(latest_pv.verification_attempt, 0) as verification_attempt,
        latest_pv.verification_notes,
        latest_pv.amount_verified,
        latest_pv.created_at as verification_date,
        latest_pv.transfer_slip
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
    LEFT JOIN (
        SELECT pv.* 
        FROM payment_verifications pv
        JOIN (
            SELECT order_id, MAX(verification_id) as latest_verification_id
            FROM payment_verifications
            GROUP BY order_id
        ) latest ON pv.verification_id = latest.latest_verification_id
    ) latest_pv ON o.order_id = latest_pv.order_id
    WHERE o.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Get order items
$stmt = $mysqli->prepare("
    SELECT oi.*, mk.name as meal_kit_name, mk.image_url
    FROM order_items oi
    LEFT JOIN meal_kits mk ON oi.meal_kit_id = mk.meal_kit_id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

// Fetch customizations for each order item
$order_items = [];
while ($item = $items->fetch_assoc()) {
    // Get ingredient customizations
    $ing_stmt = $mysqli->prepare("
        SELECT oii.*, ing.name as ingredient_name
        FROM order_item_ingredients oii
        LEFT JOIN ingredients ing ON oii.ingredient_id = ing.ingredient_id
        WHERE oii.order_item_id = ?
    ");
    $ing_stmt->bind_param("i", $item['order_item_id']);
    $ing_stmt->execute();
    $customizations = $ing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $item['customizations'] = $customizations;
    $order_items[] = $item;
}

// Get payment history
$payment_stmt = $mysqli->prepare("
    SELECT ph.*, ps.payment_method, COALESCE(latest_pv.payment_status, 0) as payment_status, latest_pv.verification_notes 
    FROM payment_history ph
    LEFT JOIN payment_settings ps ON ph.payment_method_id = ps.id
    LEFT JOIN (
        SELECT pv.* 
        FROM payment_verifications pv
        JOIN (
            SELECT payment_id, MAX(verification_id) as latest_verification_id
            FROM payment_verifications
            WHERE payment_id IS NOT NULL
            GROUP BY payment_id
        ) latest ON pv.verification_id = latest.latest_verification_id
    ) latest_pv ON ph.payment_id = latest_pv.payment_id
    WHERE ph.order_id = ?
    ORDER BY ph.created_at DESC
");
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payment_history = $payment_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if the latest payment status is failed (status = 2)
$latest_payment_status = null;
$payment_notes = '';
if (!empty($payment_history)) {
    $latest_payment_status = $payment_history[0]['payment_status'];
    $payment_notes = $payment_history[0]['verification_notes'] ?? '';
}

// Get order notifications
$notification_stmt = $mysqli->prepare("
    SELECT * FROM order_notifications
    WHERE order_id = ? AND user_id = ?
    ORDER BY created_at DESC
");
$notification_stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$notification_stmt->execute();
$notifications = $notification_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Use the same image logic as meal-kits.php
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0];
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

// Format payment status with appropriate badge
function get_payment_status_badge($status) {
    $status = (int)$status;
    return match($status) {
        0 => '<span class="badge bg-warning text-dark">Pending</span>',
        1 => '<span class="badge bg-success">Completed</span>',
        2 => '<span class="badge bg-danger">Failed</span>',
        3 => '<span class="badge bg-info">Refunded</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

// Get payment status from latest verification
$paymentStatusText = "Not Available";
$paymentStatusClass = "secondary";

// Update the query to get the latest verification directly by order_id
$verificationStmt = $mysqli->prepare("
    SELECT payment_status, verification_attempt, created_at, amount_verified
    FROM payment_verifications 
    WHERE order_id = ?
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($verificationStmt) {
    $verificationStmt->bind_param("i", $order_id);
    $verificationStmt->execute();
    $verificationResult = $verificationStmt->get_result();
    
    if ($verificationRow = $verificationResult->fetch_assoc()) {
        $paymentStatusCode = (int)$verificationRow['payment_status']; 
        $paymentStatusText = getPaymentStatusText($paymentStatusCode);
        $paymentStatusClass = getPaymentStatusClass($paymentStatusCode);
        $verificationAttempt = $verificationRow['verification_attempt'];
        $verificationTime = $verificationRow['created_at'];
        $verifiedAmount = $verificationRow['amount_verified'];
    } else {
        // If no verification record is found, use "Pending" as default for orders with a transfer slip
        if (!empty($order['transfer_slip'])) {
            $paymentStatusText = "Pending";
            $paymentStatusClass = "warning";
        }
    }
    $verificationStmt->close();
}

// Format order status
$statusRaw = strtolower(trim($order['status_name'] ?? $order['status'] ?? ''));
$statusMap = [
    'pending' => 'warning',
    'processing' => 'info',
    'shipped' => 'primary',
    'delivered' => 'success',
    'cancelled' => 'danger',
];
$statusClass = $statusMap[$statusRaw] ?? 'secondary';

$html = "<div class='order-details'>";
$html .= "<div class='row mb-4'>";
$html .= "<div class='col-md-6 mb-3 mb-md-0'>";
$html .= "<div class='p-3 rounded-4 shadow-sm h-100' style='background:linear-gradient(90deg,#e3f2fd 60%,#bbdefb 100%); border-left:6px solid #1976d2;'>";
$html .= "<h6 class='fw-bold mb-2' style='color:#1976d2;'><i class='bi bi-receipt'></i> Order Information</h6>";
$html .= "<p class='mb-1'><strong>Order ID:</strong> <span style='color:#1976d2;'>#" . $order['order_id'] . "</span></p>";
$html .= "<p class='mb-1'><strong>Date:</strong> <span style='color:#0288d1;'>" . date('F d, Y h:i A', strtotime($order['created_at'])) . "</span></p>";
$html .= "<p class='mb-1'><strong>Status:</strong> <span class='badge bg-" . $statusClass . " fw-bold px-3 py-2 rounded-pill' style='font-size:1em;min-width:110px;letter-spacing:0.5px;'>" . htmlspecialchars($order['status_name'] ?? $order['status'] ?? '') . "</span></p>";
$html .= "<p class='mb-1'><strong>Payment Method:</strong> <span style='color:#388e3c;'>" . htmlspecialchars($order['payment_method']) . "</span></p>";
$html .= "<p class='mb-1'><strong>Payment Status:</strong> <span class='badge bg-" . $paymentStatusClass . " fw-bold px-3 py-2 rounded-pill' style='font-size:1em;min-width:110px;letter-spacing:0.5px;'>" . $paymentStatusText . "</span></p>";

// Display refund message if payment was refunded
if ($order['payment_status'] == 3) {
    $html .= "<div class='alert alert-info mt-3'>
        <i class='bi bi-info-circle-fill me-2'></i>
        <strong>Payment Refunded</strong>
        <p class='mb-0 mt-1'>This order has been cancelled and the payment has been refunded.</p>
        " . (!empty($order['verification_notes']) ? "<p class='mt-2 mb-0'><strong>Reason:</strong> " . htmlspecialchars($order['verification_notes']) . "</p>" : "") . "
    </div>";
}

if (isset($verificationAttempt) && $verificationAttempt > 1) {
    $html .= "<p class='mb-1 mt-2 text-info'><i class='bi bi-info-circle'></i> <small>Verification attempt: " . $verificationAttempt . "</small></p>";
}

$html .= "</div></div>";
$html .= "<div class='col-md-6'>";
$html .= "<div class='p-3 h-100 border rounded bg-light-subtle'>";
$html .= "<h6 class='fw-bold mb-2 text-success'><i class='bi bi-truck me-1'></i>Delivery Information</h6>";
$html .= "<p class='mb-1'><span class='fw-semibold'>Delivery Method:</span> " . htmlspecialchars($order['shipping_method'] ?? 'Standard Delivery') . "</p>";
$html .= "<p class='mb-1'><span class='fw-semibold'>Delivery Fee:</span> " . number_format($order['delivery_fee']) . " MMK</p>";
if (!empty($order['expected_delivery_date'])) {
    $html .= "<p class='mb-1'><span class='fw-semibold'>Expected Delivery:</span> " . htmlspecialchars(date('F d, Y', strtotime($order['expected_delivery_date']))) . "</p>";
}
if (!empty($order['preferred_delivery_time'])) {
    $html .= "<p class='mb-1'><span class='fw-semibold'>Delivery Time:</span> " . htmlspecialchars(date('g:i A', strtotime($order['preferred_delivery_time']))) . "</p>";
}
$html .= "<p class='mb-1'><span class='fw-semibold'>Address:</span> " . htmlspecialchars($order['delivery_address']) . "</p>";
$html .= "<p class='mb-1'><span class='fw-semibold'>Customer Phone:</span> " . htmlspecialchars($order['customer_phone']) . "</p>";
$html .= "<p class='mb-1'><span class='fw-semibold'>Contact Number:</span> " . htmlspecialchars($order['contact_number']) . "</p>";
$html .= "<p class='mb-1'><span class='fw-semibold'>Notes:</span> " . htmlspecialchars($order['delivery_notes'] ?? 'None') . "</p>";
$html .= "</div></div></div></div>";

// Add notifications section with better styling
if (!empty($notifications)) {
    $html .= "<div class='mb-4'>";
    $html .= "<h6 class='fw-bold text-primary mb-2'><i class='bi bi-bell me-1'></i>Order Status Updates</h6>";
    $html .= "<div class='notification-list border rounded p-3'>";
    
    foreach ($notifications as $notification) {
        $read_class = $notification['is_read'] ? 'text-muted' : 'fw-semibold';
        $icon_class = '';
        $bg_class = '';
        
        // Determine icon and background based on notification content
        if (strpos($notification['message'], 'cancelled') !== false) {
            $icon_class = 'bi-x-circle text-danger';
            $bg_class = 'bg-danger-subtle';
        } elseif (strpos($notification['message'], 'delivered') !== false) {
            $icon_class = 'bi-check-circle text-success';
            $bg_class = 'bg-success-subtle';
        } elseif (strpos($notification['message'], 'verified') !== false) {
            $icon_class = 'bi-shield-check text-success';
            $bg_class = 'bg-success-subtle';
        } elseif (strpos($notification['message'], 'shipping') !== false || strpos($notification['message'], 'out for delivery') !== false) {
            $icon_class = 'bi-truck text-primary';
            $bg_class = 'bg-primary-subtle';
        } elseif (strpos($notification['message'], 'processing') !== false || strpos($notification['message'], 'preparing') !== false) {
            $icon_class = 'bi-gear text-info';
            $bg_class = 'bg-info-subtle';
        } else {
            $icon_class = 'bi-info-circle text-secondary';
            $bg_class = 'bg-secondary-subtle';
        }
        
        $html .= "<div class='notification-item p-3 mb-2 rounded " . $bg_class . " border-start border-4 border-primary'>";
        $html .= "<div class='d-flex'>";
        $html .= "<div class='me-3'>";
        $html .= "<i class='bi " . $icon_class . " fs-4'></i>";
        $html .= "</div>";
        $html .= "<div>";
        $html .= "<p class='mb-1 " . $read_class . "'>" . htmlspecialchars($notification['message']) . "</p>";
        
        // Add notification note if present
        if (!empty($notification['note'])) {
            $html .= "<div class='alert alert-secondary mt-2 mb-1 py-2 px-3 small'>";
            $html .= "<i class='bi bi-quote me-1'></i> <strong>Admin note:</strong> " . 
                htmlspecialchars($notification['note']) . 
            "</div>";
        }
                        
        $html .= "<small class='text-muted'>" . date('M d, Y g:i A', strtotime($notification['created_at'])) . "</small>";
        $html .= "</div></div></div>";
    }
    
    $html .= "</div></div>";
}

$html .= "<h6 class='fw-bold text-primary mb-3'><i class='bi bi-basket2 me-1'></i>Order Items</h6>";
$html .= "<div class='table-responsive'>";
$html .= "<table class='table align-middle table-hover border rounded shadow-sm bg-white'>";
$html .= "<thead class='table-primary'>";
$html .= "<tr>";
$html .= "<th>Item</th>";
$html .= "<th>Quantity</th>";
$html .= "<th>Price</th>";
$html .= "<th>Total</th>";
$html .= "</tr>";
$html .= "</thead>";
$html .= "<tbody>";

$total = 0;
foreach ($order_items as $item) {
    $itemTotal = $item['quantity'] * $item['price_per_unit'];
    $total += $itemTotal;
    $image_url = get_meal_kit_image_url($item['image_url'], $item['meal_kit_name']);
    $html .= "<tr>";
    $html .= "<td>";
    $html .= "<div class='d-flex align-items-center'>";
    $html .= "<img src='" . $image_url . "' 
                 alt='" . htmlspecialchars($item['meal_kit_name']) . "'
                 class='me-2 rounded meal-kit-thumb'
                 style='width: 70px; height: 70px; object-fit: cover; border: 2px solid #eee; background: #fff;'>";
    $html .= "<div>";
    $html .= "<h6 class='mb-0'>" . htmlspecialchars($item['meal_kit_name']) . "</h6>";
    if (!empty($item['customization_notes'])) {
        $html .= "<small class='text-muted'><strong>Special Instructions:</strong> " . 
            htmlspecialchars($item['customization_notes']) . "</small><br>";
    }
    if (!empty($item['customizations'])) {
        $html .= "<small class='text-muted'><strong>Ingredient Customizations:</strong><ul class='mb-0'>";
        foreach ($item['customizations'] as $custom) {
            $html .= "<li>" . htmlspecialchars($custom['ingredient_name']) . ": " . htmlspecialchars($custom['custom_grams']) . "g</li>";
        }
        $html .= "</ul></small>";
    }
    $html .= "</div>";
    $html .= "</div>";
    $html .= "</td>";
    $html .= "<td>" . $item['quantity'] . "</td>";
    $html .= "<td>" . number_format($item['price_per_unit']) . " MMK</td>";
    $html .= "<td>" . number_format($itemTotal) . " MMK</td>";
    $html .= "</tr>";
}

$html .= "</tbody>";
$html .= "<tfoot>";
$html .= "<tr>";
$html .= "<td colspan='3' class='text-end'><strong>Subtotal:</strong></td>";
$html .= "<td>" . number_format($order['subtotal']) . " MMK</td>";
$html .= "</tr>";
$html .= "<tr>";
$html .= "<td colspan='3' class='text-end'><strong>Tax:</strong></td>";
$html .= "<td>" . number_format($order['tax']) . " MMK</td>";
$html .= "</tr>";
$html .= "<tr>";
$html .= "<td colspan='3' class='text-end'><strong>Delivery Fee:</strong></td>";
$html .= "<td>" . number_format($order['delivery_fee']) . " MMK</td>";
$html .= "</tr>";
$html .= "<tr>";
$html .= "<td colspan='3' class='text-end'><strong>Total:</strong></td>";
$html .= "<td><strong>" . number_format($order['total_amount']) . " MMK</strong></td>";
$html .= "</tr>";
$html .= "</tfoot>";
$html .= "</table>";
$html .= "</div>";

// Payment history section
if (!empty($payment_history)) {
    $html .= "<div class='mt-4'>";
    $html .= "<h6 class='fw-bold text-primary mb-2'><i class='bi bi-credit-card me-1'></i>Payment History</h6>";
    $html .= "<div class='table-responsive'>";
    $html .= "<table class='table table-sm table-hover border rounded shadow-sm'>";
    $html .= "<thead class='table-secondary'>";
    $html .= "<tr>";
    $html .= "<th>Date</th>";
    $html .= "<th>Method</th>";
    $html .= "<th>Transaction ID</th>";
    $html .= "<th>Amount</th>";
    $html .= "<th>Status</th>";
    $html .= "</tr>";
    $html .= "</thead>";
    $html .= "<tbody>";
    
    foreach ($payment_history as $payment) {
        // We already have the latest verification status from our updated query
        $paymentStatusText = getPaymentStatusText($payment['payment_status']);
        $paymentStatusClass = getPaymentStatusClass($payment['payment_status']);
        
        $html .= "<tr>";
        $html .= "<td>" . date('M d, Y', strtotime($payment['created_at'])) . "</td>";
        $html .= "<td>" . htmlspecialchars($payment['payment_method']) . "</td>";
        $html .= "<td>" . htmlspecialchars($payment['transaction_id'] ?? 'N/A') . "</td>";
        $html .= "<td>" . number_format($payment['amount']) . " MMK</td>";
        $html .= "<td><span class='badge bg-" . $paymentStatusClass . "'>" . $paymentStatusText . "</span></td>";
        $html .= "</tr>";
    }
    
    $html .= "</tbody>";
    $html .= "</table>";
    $html .= "</div>";
    $html .= "</div>";
}

// Get latest payment verification status
$verification_stmt = $mysqli->prepare("
    SELECT payment_status, payment_verified, transfer_slip 
    FROM payment_verifications 
    WHERE order_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$verification_stmt->bind_param("i", $order_id);
$verification_stmt->execute();
$verification_result = $verification_stmt->get_result();
$latest_verification = $verification_result->fetch_assoc();
$verification_stmt->close();

$latest_payment_status = $latest_verification['payment_status'] ?? 0;
$has_payment_slip = !empty($latest_verification['transfer_slip']);
$is_payment_verified = $latest_verification['payment_verified'] ?? 0;

// Add payment slip upload section if needed - Allow resubmission if payment failed
if ($order['payment_method'] !== 'Cash on Delivery' && 
    (!$has_payment_slip || $latest_payment_status == 2) && 
    $latest_payment_status != 3 &&  // Don't allow resubmission for refunded payments
    $is_payment_verified == 0 && 
    in_array($order['status_name'], ['pending', 'confirmed', 'processing'])) {
    
    $upload_title = !$has_payment_slip ? 'Upload Payment Slip' : 'Resubmit Payment Slip';
    $warning_class = $latest_payment_status == 2 ? 'danger' : 'warning';
    $warning_icon = $latest_payment_status == 2 ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';
    
    // Create a simple card with warning and direct link to payment page
    $html .= "<div class='mt-4 p-3 border rounded bg-light-subtle shadow-sm' id='paymentSlipSection'>";
    
    // Add warning message
    if ($latest_payment_status == 2) {
        $html .= "<div class='alert alert-{$warning_class} mb-3'>
            <i class='bi {$warning_icon} me-2'></i> 
            <strong>Payment Verification Failed</strong>";
        
        // Show verification notes if available
        if (!empty($order['verification_notes'])) {
            $html .= "<p class='mt-2 mb-1'><strong>Reason:</strong> " . htmlspecialchars($order['verification_notes']) . "</p>";
        }
        
        $html .= "<p class='mb-0 mt-1'>Please resubmit your payment slip.</p>
        </div>";
    } else if (!$has_payment_slip) {
        $html .= "<div class='alert alert-{$warning_class} mb-3'>
            <i class='bi {$warning_icon} me-2'></i> 
            <strong>Payment Required</strong><br>
            Please upload your payment slip to complete this order.
        </div>";
    }
    
    // If there's a previous payment slip, show it
    if ($has_payment_slip) {
        $file_ext = strtolower(pathinfo($latest_verification['transfer_slip'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
            $html .= "<div class='mb-3'>
                <strong>Previous Payment Slip:</strong><br>
                <div class='position-relative mt-2'>
                    <div class='badge bg-danger position-absolute top-0 end-0 m-2'>Failed Verification</div>
                    <img src='" . htmlspecialchars($latest_verification['transfer_slip']) . "' 
                        class='img-fluid rounded shadow-sm border' style='max-height: 200px;'>
                </div>
                <div class='mt-2'>
                    <button type='button' class='btn btn-sm btn-outline-primary view-image-btn' data-img-src='" . htmlspecialchars($latest_verification['transfer_slip']) . "'>
                        <i class='bi bi-zoom-in'></i> View Larger
                    </button>
                </div>
            </div>";
        } elseif ($file_ext === 'pdf') {
            $html .= "<div class='mb-3'>
                <strong>Previous Payment Slip:</strong><br>
                <div class='p-3 bg-white rounded shadow-sm border text-center mt-2' style='max-width: 200px;'>
                    <div class='position-relative'>
                        <div class='badge bg-danger position-absolute top-0 end-0 m-2'>Failed</div>
                        <i class='bi bi-file-earmark-pdf text-danger' style='font-size: 48px;'></i>
                        <p class='mt-2 mb-0'>PDF Document</p>
                    </div>
                    <a href='" . htmlspecialchars($latest_verification['transfer_slip']) . "' target='_blank' class='btn btn-sm btn-primary mt-2'>
                        <i class='bi bi-eye'></i> View PDF
                    </a>
                </div>
            </div>";
        }
    }
    
    // Add upload form
    $html .= "<form id='paymentSlipForm' enctype='multipart/form-data'>
        <input type='hidden' name='order_id' value='" . $order_id . "'>
        
        <div class='upload-container border border-2 border-dashed rounded-3 p-4 text-center mb-3' 
             style='border-color: rgba(0, 123, 255, 0.3); background: rgba(0, 123, 255, 0.03);'>
            
            <input type='file' name='transfer_slip' class='form-control d-none' id='transferSlip' 
                accept='image/jpeg,image/png,image/jpg,application/pdf'>
            
            <label for='transferSlip' class='mb-2 d-block' style='cursor: pointer;'>
                <i class='bi bi-cloud-arrow-up fs-3 d-block mb-2 text-primary'></i>
                <span id='fileSelectionText'>Click to select payment slip image or PDF</span>
            </label>
            
            <div id='previewContainer' class='text-center d-none mt-3'>
                <!-- Image preview -->
                <div id='imagePreview' class='d-none'>
                    <div class='position-relative mb-2'>
                        <img src='' id='previewImg' class='img-fluid rounded mx-auto d-block shadow-sm' 
                            style='max-height: 250px; max-width: 100%; border: 1px solid #dee2e6;'>
                        <span class='position-absolute top-0 end-0 badge rounded-pill bg-primary m-2' id='previewBadge'>
                            <i class='bi bi-arrow-repeat me-1'></i>Resubmission
                        </span>
                    </div>
                    <div class='mt-2 small text-muted'>Image preview</div>
                </div>
                
                <!-- PDF preview -->
                <div id='pdfPreview' class='d-none py-4 bg-white rounded border shadow-sm' style='max-width: 200px; margin: 0 auto;'>
                    <i class='bi bi-file-earmark-pdf text-danger' style='font-size: 48px;'></i>
                    <p class='mt-2 mb-0 fw-semibold'>PDF Document</p>
                    <span class='badge rounded-pill bg-primary my-2' id='pdfPreviewBadge'>
                        <i class='bi bi-arrow-repeat me-1'></i>Resubmission
                    </span>
                    <div class='mt-1 small text-muted'>PDF selected</div>
                </div>
                
                <div class='d-flex justify-content-center gap-2 mt-3'>
                    <!-- View button for images -->
                    <button type='button' class='btn btn-sm btn-outline-primary' id='viewImageBtn'>
                        <i class='bi bi-fullscreen'></i> View Larger
                    </button>
                    <!-- Remove button -->
                    <button type='button' class='btn btn-sm btn-outline-danger' id='removeFileBtn'>
                        <i class='bi bi-trash'></i> Remove
                    </button>
                </div>
            </div>
            
            <div id='fileHint' class='form-text mt-2'>Only JPEG, PNG or PDF files are accepted (max 5MB)</div>
        </div>

        <!-- Error alert (initially hidden) -->
        <div class='alert alert-danger d-none' id='uploadError'>
            <i class='bi bi-exclamation-triangle-fill me-2'></i>
            <span id='errorMessage'>Please select a file before submitting</span>
        </div>

        <!-- Success alert (initially hidden) -->
        <div class='alert alert-success d-none' id='uploadSuccess'>
            <i class='bi bi-check-circle-fill me-2'></i>
            <span id='successMessage'>Payment slip uploaded successfully</span>
        </div>
        
        <!-- Success alert (initially hidden) -->
        <div class='alert alert-success alert-dismissible fade show d-none' id='uploadSuccess' role='alert'>
            <div class='d-flex align-items-center'>
                <i class='bi bi-check-circle-fill fs-4 me-2'></i>
                <div>
                    <strong>Success!</strong> <span id='successMessage'>Payment slip uploaded successfully</span>
                </div>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
        
        <!-- Error alert (initially hidden) -->
        <div class='alert alert-danger alert-dismissible fade show d-none' id='uploadError' role='alert'>
            <div class='d-flex align-items-center'>
                <i class='bi bi-exclamation-octagon-fill fs-4 me-2'></i>
                <div>
                    <strong>Error!</strong> <span id='errorMessage'>Please select a file before submitting</span>
                </div>
            </div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
        
        <div class='d-grid gap-2 d-md-flex'>
            <a href='payment-resubmit.php?order_id=" . $order_id . "' class='btn btn-outline-primary flex-grow-1'>
                <i class='bi bi-arrow-up-square me-2'></i>Use Dedicated Upload Page
            </a>
            <button type='button' class='btn btn-primary flex-grow-1 orderDetailsSubmitBtn' id='submitPaymentBtn'>
                <i class='bi bi-upload me-2'></i>" . $upload_title . "
            </button>
        </div>
    </form>";
    
    // Add JavaScript for preview functionality
    $html .= "<script>
    // Dynamically load Animate.css if not already loaded
    if (!document.querySelector('link[href*=\"animate.css\"]')) {
        var animateCSS = document.createElement('link');
        animateCSS.rel = 'stylesheet';
        animateCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
        document.head.appendChild(animateCSS);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('transferSlip');
        const fileSelectionText = document.getElementById('fileSelectionText');
        const previewContainer = document.getElementById('previewContainer');
        const imagePreview = document.getElementById('imagePreview');
        const pdfPreview = document.getElementById('pdfPreview');
        const previewImg = document.getElementById('previewImg');
        const removeFileBtn = document.getElementById('removeFileBtn');
        const viewImageBtn = document.getElementById('viewImageBtn');
        const uploadContainer = document.querySelector('.upload-container');
        const errorDiv = document.getElementById('uploadError');
        const errorMessage = document.getElementById('errorMessage');
        
        // Initialize Bootstrap alert dismissal
        if (errorDiv) {
            const closeBtn = errorDiv.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    errorDiv.classList.add('d-none');
                });
            }
        }
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                
                if (file) {
                    // Hide error alert if visible
                    if (errorDiv) {
                        errorDiv.classList.add('d-none');
                    }
                    
                    fileSelectionText.textContent = 'Selected: ' + file.name;
                    previewContainer.classList.remove('d-none');
                    uploadContainer.style.borderColor = 'rgba(25, 135, 84, 0.5)';
                    uploadContainer.style.background = 'rgba(25, 135, 84, 0.03)';
                    
                    try {
                        if (file.type.indexOf('image') > -1) {
                            // For images
                            imagePreview.classList.remove('d-none');
                            pdfPreview.classList.add('d-none');
                            
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                        } 
                        else if (file.type === 'application/pdf') {
                            // For PDFs
                            imagePreview.classList.add('d-none');
                            pdfPreview.classList.remove('d-none');
                        }
                    } catch(err) {
                        console.log('Preview error:', err);
                    }
                }
            });
        }
        
        // Remove file button
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function() {
                fileInput.value = '';
                previewContainer.classList.add('d-none');
                fileSelectionText.textContent = 'Click to select payment slip image or PDF';
                uploadContainer.style.borderColor = 'rgba(0, 123, 255, 0.3)';
                uploadContainer.style.background = 'rgba(0, 123, 255, 0.03)';
            });
        }
        
        // View image button
        if (viewImageBtn) {
            viewImageBtn.addEventListener('click', function() {
                if (previewImg && previewImg.src) {
                    createImagePreviewModal(previewImg.src);
                }
            });
        }
        
        // View image buttons for existing slips
        document.querySelectorAll('.view-image-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const imgSrc = this.getAttribute('data-img-src');
                if (imgSrc) {
                    createImagePreviewModal(imgSrc);
                }
            });
        });
        
        function createImagePreviewModal(imgSrc) {
            // Remove any existing modal
            const existingModal = document.getElementById('imagePreviewModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Create modal HTML
            const modalHTML = `
                <div class='modal fade' id='imagePreviewModal' tabindex='-1' aria-hidden='true'>
                    <div class='modal-dialog modal-lg modal-dialog-centered'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title'>Payment Slip Preview</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body text-center'>
                                <img src='${imgSrc}' class='img-fluid rounded' alt='Payment slip preview' style='max-height: 70vh;'>
                            </div>
                            <div class='modal-footer'>
                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add modal to the document
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
            modal.show();
        }
    });
    </script>";
    
    $html .= "</div>";
}

// Action buttons
$html .= "<div class='d-flex justify-content-end mt-3'>";
$html .= "<div class='btn-group'>";
$html .= "<button type='button' class='btn btn-primary' onclick='reorderItems(" . $order_id . ")'><i class='bi bi-cart-plus'></i> Reorder</button>";
if ($order['status_id'] == 1) {
    $html .= "<button type='button' class='btn btn-danger ms-2' onclick='cancelOrder(" . $order_id . ")'><i class='bi bi-x-circle'></i> Cancel Order</button>";
}
$html .= "</div></div></div>";

// Mark notifications as read
if (!empty($notifications)) {
    $mark_read_stmt = $mysqli->prepare("
        UPDATE order_notifications 
        SET is_read = 1 
        WHERE order_id = ? AND user_id = ? AND is_read = 0
    ");
    $mark_read_stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
    $mark_read_stmt->execute();
}

$html .= "</div>";

echo json_encode([
    'success' => true,
    'html' => $html,
    'order' => [
        'order_id' => $order_id,
        'status' => $order['status_name'],
        'created_at' => $order['created_at'],
        'expected_delivery_date' => $order['expected_delivery_date'],
        'total_amount' => $order['total_amount']
    ]
]); 