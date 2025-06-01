<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role != 1) {
    header("Location: /hm/login.php");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /hm/admin/orders.php");
    exit();
}

$order_id = (int)$_GET['id'];

// Get order details
$stmt = $mysqli->prepare("
    SELECT 
        o.*,
        os.status_name,
        u.full_name as customer_name,
        u.email as customer_email,
        ps.payment_method,
        ps.account_phone as company_account_phone,
        
        lph.payment_id AS latest_payment_history_id,
        lph.transaction_id AS latest_transaction_id,

        lpv.payment_status AS current_payment_status_val,
        lpv.transfer_slip AS current_transfer_slip,
        lpv.payment_verified AS current_payment_verified,
        lpv.payment_verified_at AS current_payment_verified_at,
        lpv.verification_notes AS current_verification_notes,
        lpv.amount_verified AS current_amount_verified,
        lpv.transaction_id AS current_verification_transaction_id,
        lpv.verification_attempt AS current_verification_attempt,
        lpv.resubmission_status AS current_resubmission_status,
        verifier.full_name AS verified_by_admin_name

    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_status os ON o.status_id = os.status_id
    LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
    
    LEFT JOIN (
        SELECT ph1.*
        FROM payment_history ph1
        LEFT JOIN payment_history ph2 ON ph1.order_id = ph2.order_id AND ph1.payment_id < ph2.payment_id
        WHERE ph2.payment_id IS NULL
    ) lph ON o.order_id = lph.order_id

    LEFT JOIN (
        SELECT pv1.*
        FROM payment_verifications pv1
        LEFT JOIN payment_verifications pv2
            ON pv1.order_id = pv2.order_id
            AND (pv1.created_at < pv2.created_at OR (pv1.created_at = pv2.created_at AND pv1.verification_id < pv2.verification_id))
        WHERE pv2.verification_id IS NULL
    ) lpv ON o.order_id = lpv.order_id
    LEFT JOIN users verifier ON lpv.verified_by_id = verifier.user_id
    WHERE o.order_id = ?
");

if (!$stmt) {
    die('SQL Error: ' . $mysqli->error);
}

$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

// Initialize payment status variables to prevent undefined warnings
$paymentStatusText = "Not Available";
$paymentStatusClass = "secondary";

if (!$order) {
    // Order not found, redirect or show error, but ensure variables are set if HTML relies on them
    // For safety, one might echo an error message and exit, or redirect.
    // If the page structure still attempts to render parts of the layout, ensure all needed vars are safe.
    header("Location: /hm/admin/orders.php?error=notfound");
    exit();
}

// Get order items
$items_stmt = $mysqli->prepare("
    SELECT 
        oi.*, 
        mk.name as meal_kit_name, 
        mk.image_url, 
        mk.base_calories, 
        mk.cooking_time, 
        mk.servings
    FROM order_items oi
    JOIN meal_kits mk ON oi.meal_kit_id = mk.meal_kit_id
    WHERE oi.order_id = ?
");

$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = [];

$subtotal = 0;
while ($item = $items_result->fetch_assoc()) {
    // Fetch ingredient customizations
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

// Get all order statuses for dropdown
$statuses = [];
$status_result = $mysqli->query("SELECT * FROM order_status ORDER BY status_id");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $statuses[] = $row;
    }
}

// Helper function to get image URL
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

// Helper function to get file path
function get_absolute_file_path($relative_path) {
    if (!$relative_path) return '';
    if (preg_match('/^https?:\/\//i', $relative_path)) {
        return $relative_path;
    }
    
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0]; // e.g. '/hm'
    
    if (strpos($relative_path, $projectBase) === 0) {
        return $relative_path;
    }
    
    if (strpos($relative_path, '/uploads/') === 0) {
        return $projectBase . $relative_path;
    }
    
    return $projectBase . '/' . ltrim($relative_path, '/');
}

// Get payment status text and class based on the new field current_payment_status_val
$currentPaymentStatusNumeric = isset($order['current_payment_status_val']) ? (int)$order['current_payment_status_val'] : -1; // -1 for undefined

if ($currentPaymentStatusNumeric !== -1) {
    switch($currentPaymentStatusNumeric) {
        case 0: // Pending
            $paymentStatusText = "Pending";
            $paymentStatusClass = "warning";
            break;
        case 1: // Completed
            $paymentStatusText = "Completed";
            $paymentStatusClass = "success";
            break;
        case 2: // Failed
            $paymentStatusText = "Failed";
            $paymentStatusClass = "danger";
            break;
        case 3: // Refunded
            $paymentStatusText = "Refunded";
            $paymentStatusClass = "info";
            break;
        case 4: // Partial (if used)
            $paymentStatusText = "Partial";
            $paymentStatusClass = "warning";
            break;
        default:
            $paymentStatusText = "Unknown";
            $paymentStatusClass = "secondary";
            break;
    }
}

// Format order status
$statusRaw = strtolower(trim($order['status_name'] ?? 'unknown'));
$statusMap = [
    'pending' => 'warning',
    'confirmed' => 'primary', // Example: 'confirmed' might map to 'primary'
    'preparing' => 'info',
    'processing' => 'info', // Added processing as it's common
    'shipped' => 'primary',
    'out_for_delivery' => 'primary', // Example for a common status
    'delivered' => 'success',
    'failed_delivery' => 'danger', // Example for a common status
    'cancelled' => 'danger',
];
$statusClass = $statusMap[$statusRaw] ?? 'secondary';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order['order_id']; ?> - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <!-- Toastr CSS for toast notifications -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .order-details-page {
            max-width: 1200px;
            margin: 0 auto;
        }
        .back-button {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            color: #6c757d;
            text-decoration: none;
            transition: all 0.2s;
        }
        .back-button:hover {
            color: #343a40;
            transform: translateX(-5px);
        }
        .order-header {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            padding: 2rem;
        }
        .order-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .order-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            padding: 2rem;
        }
        .order-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #343a40;
            display: flex;
            align-items: center;
        }
        .order-section-title i {
            margin-right: 0.5rem;
            color: #6c757d;
        }
        .order-section-divider {
            height: 1px;
            background: #e9ecef;
            margin: 1.5rem 0;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.85rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 0.5rem;
        }
        .order-status-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 0.5rem;
            margin-bottom: 1rem;
        }
        .status-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
        }
        .order-item {
            display: flex;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .order-item:hover {
            box-shadow: 0 3px 6px rgba(0,0,0,0.04);
            transform: translateY(-3px);
        }
        .order-item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 1.5rem;
        }
        .order-item-details {
            flex: 1;
        }
        .order-item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .order-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .order-item-meta-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .order-item-meta-item i {
            margin-right: 0.35rem;
            font-size: 0.75rem;
        }
        .order-item-pricing {
            text-align: right;
            min-width: 150px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            justify-content: center;
            margin-left: auto;
            border-left: 1px solid #eee;
            padding-left: 20px;
        }
        .order-item-price, .order-item-quantity, .order-item-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .order-item-price {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .order-item-quantity {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .order-item-total {
            font-weight: 600;
            font-size: 1rem;
            color: #343a40;
            padding-top: 8px;
            border-top: 1px dashed #e9ecef;
        }
        .price-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: normal;
            margin-right: auto;
        }
        .price-value {
            font-weight: 500;
        }
        .order-item-total .price-value {
            font-weight: 700;
            color: #212529;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        .summary-label {
            color: #6c757d;
        }
        .summary-value {
            font-weight: 500;
        }
        .summary-total {
            font-weight: 700;
            font-size: 1.25rem;
            color: #212529;
        }
        .payment-slip {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .payment-verified-badge {
            display: inline-flex;
            align-items: center;
            background: #d1e7dd;
            color: #146c43;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .payment-verified-badge i {
            margin-right: 0.5rem;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .info-group {
            margin-bottom: 1rem;
        }
        .info-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: 500;
        }
        .custom-ingredients {
            background: #f0f4f8;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        .custom-ingredient {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        .verification-details {
            background: #f0f4f8;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .verification-row {
            margin-bottom: 0.75rem;
        }
        @media (max-width: 767px) {
            .order-item {
                flex-direction: column;
            }
            .order-item-image {
                width: 100%;
                height: 180px;
                margin-right: 0;
                margin-bottom: 1rem;
            }
            .order-item-pricing {
                text-align: left;
                border-left: none;
                padding-left: 0;
                padding-top: 15px;
                margin-top: 15px;
                border-top: 1px solid #eee;
                width: 100%;
            }
        }
        
        /* Timeline styling for payment history */
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 24px;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding-left: 60px;
        }
        .timeline-item.first:before {
            content: '';
            position: absolute;
            top: -20px;
            left: 24px;
            width: 2px;
            height: 20px;
            background: white;
        }
        .timeline-badge {
            position: absolute;
            top: 0;
            left: 14px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            text-align: center;
            color: white;
            line-height: 20px;
        }
        .timeline-badge i {
            font-size: 12px;
        }
        .timeline-content {
            background: white;
        }
    </style>
</head>
<body>

<div class="overlay" onclick="toggleSidebar()"></div>
<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="sidebar-toggle">
        <button class="btn btn-dark" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <main class="main-content">
        <div class="order-details-page py-4">
            <!-- Alert for messages -->
            <div id="orderMessage"></div>
            
            <!-- Back button -->
            <a href="orders.php" class="back-button">
                <i class="bi bi-arrow-left me-2"></i> Back to Orders
            </a>
            
            <!-- Order Header -->
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1>Order #<?php echo $order['order_id']; ?></h1>
                        <p class="text-muted mb-0">
                            <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="d-flex flex-column align-items-end mb-3">
                            <div class="order-status-container p-3 bg-light w-100 text-start rounded-3 border shadow-sm">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="status-label">Order Status</div>
                                        <div class="status-label">Payment Status</div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="status-badge bg-<?php echo $statusClass; ?>">
                                            <i class="bi bi-box-seam me-1"></i> <?php echo htmlspecialchars($order['status_name']); ?>
                                        </span>
                                        <span class="status-badge bg-<?php echo $paymentStatusClass; ?>">
                                            <i class="bi bi-credit-card me-1"></i> <?php echo $paymentStatusText; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="orderStatus" class="form-label fw-bold">Change Order Status:</label>
                                    <select class="form-select" id="orderStatus" 
                                            data-order-id="<?php echo $order['order_id']; ?>"
                                            data-original-status="<?php echo $order['status_id']; ?>">
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status['status_id']; ?>" 
                                                <?php echo ($status['status_id'] == $order['status_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['status_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="row">
                <div class="col-md-6">
                    <div class="order-section">
                        <h2 class="order-section-title">
                            <i class="bi bi-person-circle"></i> Customer Information
                        </h2>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['contact_number']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Delivery Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['delivery_address']); ?></div>
                        </div>
                        <?php if (!empty($order['delivery_notes'])): ?>
                        <div class="info-group">
                            <div class="info-label">Delivery Notes</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['delivery_notes']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="order-section">
                        <h2 class="order-section-title">
                            <i class="bi bi-credit-card"></i> Payment Information
                        </h2>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Payment Method</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['payment_method']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group">
                                    <div class="info-label">Payment Status</div>
                                    <div class="info-value">
                                        <div class="payment-status-box bg-<?php echo $paymentStatusClass; ?>-subtle p-2 rounded-3 border border-<?php echo $paymentStatusClass; ?> text-<?php echo $paymentStatusClass; ?>">
                                            <i class="bi bi-<?php 
                                                switch($paymentStatusClass) {
                                                    case 'success': echo 'check-circle-fill'; break;
                                                    case 'warning': echo 'exclamation-triangle-fill'; break;
                                                    case 'danger': echo 'x-circle-fill'; break;
                                                    case 'info': echo 'arrow-repeat'; break;
                                                    default: echo 'question-circle-fill';
                                                }
                                            ?> me-2"></i>
                                            <strong><?php echo $paymentStatusText; ?></strong>
                                            
                                            <?php if ($currentPaymentStatusNumeric == 2): // Failed payment ?>
                                            <span class="badge bg-warning text-dark ms-2">Needs Resubmission</span>
                                            <?php elseif ($order['current_resubmission_status'] == 1): // Resubmitted ?>
                                            <span class="badge bg-primary ms-2">Resubmitted</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['current_verification_attempt'] > 1): ?>
                                            <span class="badge bg-info ms-2">Attempt <?php echo $order['current_verification_attempt']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($currentPaymentStatusNumeric == 2): // Failed payment ?>
                                        <div class="alert alert-warning mt-2">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                            <strong>Payment verification failed.</strong> Customer needs to resubmit payment.
                                            <?php if (!empty($order['current_verification_notes'])): ?>
                                            <div class="mt-1 small">
                                                <strong>Note:</strong> <?php echo htmlspecialchars($order['current_verification_notes']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Order Status</div>
                            <div class="info-value">
                                <div class="order-status-box bg-<?php echo $statusClass; ?>-subtle p-2 rounded-3 border border-<?php echo $statusClass; ?> text-<?php echo $statusClass; ?>">
                                    <i class="bi bi-<?php 
                                        switch($statusClass) {
                                            case 'success': echo 'check-circle-fill'; break;
                                            case 'warning': echo 'hourglass-split'; break;
                                            case 'primary': echo 'truck'; break;
                                            case 'info': echo 'gear'; break;
                                            case 'danger': echo 'x-circle-fill'; break;
                                            default: echo 'box-seam';
                                        }
                                    ?> me-2"></i>
                                    <strong><?php echo htmlspecialchars($order['status_name']); ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($order['latest_transaction_id'])): ?>
                        <div class="info-group">
                            <div class="info-label">Transaction ID (from Payment History)</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['latest_transaction_id']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['current_transfer_slip'])): ?>
                        <div class="info-group">
                            <div class="info-label">Payment Slip</div>
                            <a href="<?php echo get_absolute_file_path($order['current_transfer_slip']); ?>" target="_blank">
                                <img src="<?php echo get_absolute_file_path($order['current_transfer_slip']); ?>" class="payment-slip" alt="Payment Slip">
                            </a>
                            
                            <?php if ($order['current_payment_verified'] == 1): ?>
                            <div class="payment-verified-badge">
                                <i class="bi bi-check-circle-fill"></i> Payment Verified
                                <?php if (!empty($order['current_payment_verified_at'])): ?>
                                    on <?php echo date('F d, Y h:i A', strtotime($order['current_payment_verified_at'])); ?>
                                <?php endif; ?>
                                <?php if (!empty($order['verified_by_admin_name'])): ?>
                                    by <?php echo htmlspecialchars($order['verified_by_admin_name']); ?>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($order['payment_method'] !== 'Cash on Delivery' && $order['current_payment_status_val'] != 1 && $order['current_payment_status_val'] != 3 ): // No slip, not COD, not completed or refunded ?>
                        <div class="info-group">
                            <div class="info-label">Payment Slip</div>
                            <p class="text-muted">No payment slip uploaded yet.</p>
                            <?php 
                            // Only show manual verification button if payment has not been marked as failed
                            if ($order['current_payment_status_val'] != 2): ?>
                             <button type="button" class="btn btn-success" onclick="verifyPayment(<?php echo $order['order_id']; ?>, false, <?php echo $order['total_amount']; ?>)">
                                <i class="bi bi-shield-check me-2"></i> Manually Verify / Record Payment
                             </button>
                            <?php else: ?>
                             <p class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Payment was marked as failed. User must resubmit payment before verification.</p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php // Display latest verification details if they exist from the main query
                        if (!empty($order['current_verification_transaction_id']) || !empty($order['current_verification_notes'])): ?>
                        <div class="verification-details">
                            <h6 class="mb-3">Latest Verification Attempt</h6>
                            <?php if (!empty($order['verified_by_admin_name'])): ?>
                            <div class="verification-row">
                                <div class="info-label">Verified By</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['verified_by_admin_name']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($order['current_verification_transaction_id'])): ?>
                            <div class="verification-row">
                                <div class="info-label">Transaction ID (from slip)</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['current_verification_transaction_id']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($order['current_amount_verified'])): ?>
                            <div class="verification-row">
                                <div class="info-label">Amount Verified</div>
                                <div class="info-value"><?php echo number_format($order['current_amount_verified'], 0); ?> MMK</div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($order['current_verification_notes'])): ?>
                            <div class="verification-row">
                                <div class="info-label">Notes</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['current_verification_notes']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['payment_method'] !== 'Cash on Delivery'): ?>
                        <div class="payment-actions mt-3 d-flex flex-wrap gap-2">
                            <?php 
                            // Determine if this is a valid payment to verify
                            $canVerifyPayment = false;
                            $isResubmission = false;
                            $needsResubmit = false;
                            
                            // Check if this needs resubmission
                            if ($currentPaymentStatusNumeric == 2) {
                                $needsResubmit = true;
                            }
                            
                            // Check if this is a resubmission
                            if ($order['current_resubmission_status'] == 1) {
                                $isResubmission = true;
                            }
                            
                            // Check if payment can be verified:
                            // 1. Not already completed (status 1) or refunded (status 3)
                            // 2. Either:
                            //    a. Not previously failed (status 2)
                            //    b. OR properly resubmitted by user (resubmission_status == 1)
                            if ($currentPaymentStatusNumeric != 1 && $currentPaymentStatusNumeric != 3) {
                                if ($currentPaymentStatusNumeric != 2) {
                                    // Payment not failed, can be verified
                                    $canVerifyPayment = true;
                                } else if ($isResubmission) {
                                    // Payment was failed but properly resubmitted, can be verified
                                    $canVerifyPayment = true;
                                }
                            }
                            
                            // Show verify payment button only if allowed
                            if ($canVerifyPayment): ?>
                            <button type="button" class="btn btn-primary" onclick="verifyPayment(<?php echo $order['order_id']; ?>, <?php echo $isResubmission ? 'true' : 'false'; ?>, <?php echo $order['total_amount']; ?>)">
                                <i class="bi bi-shield-check me-2"></i>
                                <?php if ($isResubmission): ?>
                                    Verify Resubmitted Payment
                                <?php elseif ($order['current_payment_verified'] == 1): ?>
                                    Update Verification
                                <?php else: ?>
                                    Verify Payment
                                <?php endif; ?>
                            </button>
                            <?php elseif ($needsResubmit): ?>
                            <button type="button" class="btn btn-warning" 
                                    title="Payment was marked as failed. Status will update when customer resubmits payment.">
                                <i class="bi bi-exclamation-triangle me-2"></i>Awaiting Resubmission
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="showPaymentHistory(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-clock-history me-2"></i>Verification History
                            </button>
                            
                            <button type="button" class="btn btn-outline-info" onclick="syncPaymentRecords(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-arrow-repeat me-2"></i>Sync Payment Records
                            </button>
                        </div>
                        <?php else: // Cash on Delivery payment actions ?>
                        <div class="order-section-divider mt-4 mb-4"></div>
                        
                        <div class="cod-verification-section">
                            <h4 class="mb-3">Cash on Delivery Verification</h4>
                            
                            <?php 
                            // Determine if COD payment is already verified
                            $codVerified = ($currentPaymentStatusNumeric == 1);
                            $codPending = ($currentPaymentStatusNumeric == 0);
                            
                            if ($codVerified): 
                            ?>
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="bi bi-cash-coin me-2 fs-4"></i>
                                    <div>
                                        <strong>Payment Received</strong><br>
                                        This Cash on Delivery payment has been verified as received.
                                        <?php if (!empty($order['current_verification_transaction_id'])): ?>
                                            <div class="mt-1">Receipt #: <?php echo htmlspecialchars($order['current_verification_transaction_id']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($order['current_amount_verified'])): ?>
                                            <div>Amount: <?php echo number_format($order['current_amount_verified'], 0); ?> MMK</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-warning" onclick="verifyCODPayment(<?php echo $order['order_id']; ?>, false)">
                                        <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Verification
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2 fs-4"></i>
                                    <div>
                                        <strong><?php echo $codPending ? 'Payment Pending' : 'Payment Not Yet Verified'; ?></strong><br>
                                        <?php if ($codPending): ?>
                                            This Cash on Delivery payment is pending collection. Verify when payment has been received.
                                            <div class="mt-1"><span class="badge bg-warning text-dark">Status: Pending</span></div>
                                        <?php else: ?>
                                            Please verify when payment has been received from the customer.
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <form id="codVerificationForm" class="mt-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="codReceiptNumber" class="form-label">Receipt Number</label>
                                            <input type="text" class="form-control" id="codReceiptNumber" value="<?php echo $order['current_verification_transaction_id']; ?>" placeholder="Enter receipt or reference number">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="codAmountCollected" class="form-label">Amount Collected</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="codAmountCollected" value="<?php echo $order['total_amount']; ?>">
                                                <span class="input-group-text">MMK</span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label for="codVerificationNotes" class="form-label">Verification Notes</label>
                                            <textarea class="form-control" id="codVerificationNotes" rows="2" placeholder="Optional notes about this payment"></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="button" class="btn btn-success" onclick="verifyCODPayment(<?php echo $order['order_id']; ?>, true)">
                                                <i class="bi bi-cash-coin me-2"></i>Verify Cash Payment Received
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="order-section">
                <h2 class="order-section-title">
                    <i class="bi bi-box-seam"></i> Order Items
                </h2>
                
                <?php foreach ($order_items as $item): ?>
                <div class="order-item">
                    <img src="<?php echo get_meal_kit_image_url($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['meal_kit_name']); ?>" class="order-item-image">
                    
                    <div class="order-item-details">
                        <div class="order-item-name"><?php echo htmlspecialchars($item['meal_kit_name']); ?></div>
                        
                        <div class="order-item-meta">
                            <div class="order-item-meta-item">
                                <i class="bi bi-fire"></i> <?php echo $item['base_calories']; ?> kcal
                            </div>
                            <div class="order-item-meta-item">
                                <i class="bi bi-clock"></i> <?php echo $item['cooking_time']; ?> min
                            </div>
                            <div class="order-item-meta-item">
                                <i class="bi bi-people"></i> <?php echo $item['servings']; ?> servings
                            </div>
                        </div>
                        
                        <?php if (!empty($item['customization_notes'])): ?>
                        <div class="mt-2">
                            <div class="info-label">Customization Notes</div>
                            <div class="info-value"><?php echo htmlspecialchars($item['customization_notes']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['custom_ingredients'])): ?>
                        <div class="custom-ingredients">
                            <div class="info-label mb-2">Custom Ingredients</div>
                            <?php foreach ($item['custom_ingredients'] as $ingredient): ?>
                            <div class="custom-ingredient">
                                <span><?php echo htmlspecialchars($ingredient['ingredient_name']); ?></span>
                                <span><?php echo htmlspecialchars($ingredient['custom_grams']); ?>g</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-item-pricing">
                        <div class="order-item-price">
                            <div class="price-label">Unit Price:</div>
                            <div class="price-value"><?php echo number_format($item['price_per_unit'], 0); ?> MMK</div>
                        </div>
                        
                        <div class="order-item-quantity">
                            <div class="price-label">Quantity:</div>
                            <div class="price-value"><?php echo $item['quantity']; ?></div>
                        </div>
                        
                        <div class="order-item-total">
                            <div class="price-label">Subtotal:</div>
                            <div class="price-value"><?php echo number_format($item['price_per_unit'] * $item['quantity'], 0); ?> MMK</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="order-summary mt-4">
                    <div class="summary-row">
                        <div class="summary-label">Subtotal</div>
                        <div class="summary-value"><?php echo number_format($subtotal, 0); ?> MMK</div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">Tax</div>
                        <div class="summary-value"><?php echo number_format($order['tax'], 0); ?> MMK</div>
                    </div>
                    <div class="summary-row">
                        <div class="summary-label">Delivery Fee</div>
                        <div class="summary-value"><?php echo number_format($order['delivery_fee'], 0); ?> MMK</div>
                    </div>
                    <div class="order-section-divider"></div>
                    <div class="summary-row">
                        <div class="summary-label">Total</div>
                        <div class="summary-total"><?php echo number_format($order['total_amount'], 0); ?> MMK</div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back to Orders
                </a>
                
                <?php if ($order['status_id'] != 4): // Not cancelled ?>
                <button type="button" class="btn btn-outline-danger" onclick="deleteOrder(<?php echo $order['order_id']; ?>)">
                    <i class="bi bi-trash me-2"></i> Delete Order
                </button>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Payment Verification Modal -->
<div class="modal fade" id="paymentVerificationModal" tabindex="-1" aria-labelledby="paymentVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 0.75rem; overflow: hidden;">
            <div class="modal-header border-0 p-4" style="background: linear-gradient(135deg, #0093E9 0%, #80D0C7 100%);">
                <div class="d-flex align-items-center">
                    <div class="bg-white p-2 rounded-circle shadow me-3">
                        <i class="bi bi-shield-check fs-4 text-primary"></i>
                    </div>
                    <h5 class="modal-title fs-4 fw-bold text-white m-0" id="paymentVerificationModalLabel">Verify Payment</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="paymentSlipPreview" class="text-center mb-4 p-3 bg-light rounded-4 shadow-sm">
                    <!-- Payment slip image will be shown here -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading payment details...</p>
                    </div>
                </div>
                <form id="verificationForm" class="bg-white p-4 rounded-4 shadow-sm">
                    <input type="hidden" id="verify_order_id" name="order_id" value="">
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="account_number" class="form-label fw-semibold">Customer Account</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 rounded-start-3 shadow-sm"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control bg-light border-0 rounded-end-3 shadow-sm" id="account_number" name="account_number" readonly>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span>Our Payment Account</span>
                                <span class="badge bg-success ms-2 rounded-pill">For Verification</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success-subtle border-success border-opacity-25 rounded-start-3 shadow-sm"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control bg-success-subtle border-success border-opacity-25 text-success fw-bold rounded-0 shadow-sm" id="company_account" value="" readonly>
                                <button class="btn btn-success rounded-end-3 shadow-sm" type="button" onclick="copyAccountNumber()">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <div class="form-text small mt-1">Compare with the account number on the slip.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_status" class="form-label fw-semibold">Payment Status</label>
                        <select class="form-select border-0 shadow-sm rounded-3" id="payment_status" name="payment_status">
                            <option value="1">Completed</option>
                            <option value="2">Failed</option>
                            <option value="3">Refunded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label fw-semibold">Transaction ID</label>
                        <div class="input-group">
                            <input type="text" class="form-control border-0 shadow-sm rounded-start-3" id="transaction_id" name="transaction_id" 
                                   placeholder="Enter transaction ID from the slip" required>
                            <button class="btn btn-primary rounded-end-3 shadow-sm" type="button" id="scanTransactionBtn" title="Scan Payment Slip" onclick="scanTransactionId()">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                        <div id="transaction_scan_status" class="small mt-1"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount_verified" class="form-label fw-semibold">Amount Verified (MMK)</label>
                        <div class="input-group">
                            <span class="input-group-text border-0 shadow-sm rounded-start-3">MMK</span>
                            <input type="number" class="form-control border-0 shadow-sm rounded-end-3" id="amount_verified" name="amount_verified" 
                                step="1" min="0" required>
                        </div>
                        <div class="form-text small mt-1">Amount is pre-filled from order but can be modified if needed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control border-0 shadow-sm rounded-3" id="verification_notes" name="verification_notes" 
                                  rows="3" placeholder="Add any verification notes here..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <div>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="submitPaymentVerification()">
                        <i class="bi bi-shield-check me-2"></i>Verify Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include the Tesseract.js library for OCR -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0 p-4" style="background: #f8f9fa;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock-history fs-4 text-primary me-2"></i>
                    <h5 class="modal-title fs-4 fw-bold m-0" id="paymentHistoryModalLabel">Payment Verification History</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="paymentHistoryContent" class="p-4">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Toastr JS for toast notifications -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>
<script src="assets/js/orders.js"></script>
<script>
    // Initialize toastr
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

    // Order status change handler
    document.getElementById('orderStatus').addEventListener('change', function() {
        const orderId = this.getAttribute('data-order-id');
        const statusId = this.value;
        const originalStatus = this.getAttribute('data-original-status');
        
        // Show confirmation dialog before updating
        showOrderStatusConfirm(() => {
            updateOrderStatus(orderId, statusId, this);
        }, () => {
            // Reset to original value if cancelled
            this.value = originalStatus;
        });
    });
    
    // Custom confirm modal for order status change
    function showOrderStatusConfirm(onConfirm, onCancel) {
        // Remove any existing modal
        $('#orderStatusConfirmModal').remove();
        const modalHtml = `
        <div class="modal fade" id="orderStatusConfirmModal" tabindex="-1" aria-labelledby="orderStatusConfirmLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title" id="orderStatusConfirmLabel"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Confirm Status Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                Are you sure you want to update this order's status? This will be reflected immediately.
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="orderStatusConfirmBtn">Yes, Update</button>
              </div>
            </div>
          </div>
        </div>`;
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('orderStatusConfirmModal'));
        modal.show();
        $('#orderStatusConfirmBtn').on('click', function() {
            modal.hide();
            if (onConfirm) onConfirm();
        });
        $('#orderStatusConfirmModal').on('hidden.bs.modal', function() {
            if (onCancel) onCancel();
            $('#orderStatusConfirmModal').remove();
        });
    }
    
    // Function to synchronize payment records
    function syncPaymentRecords(orderId) {
        showGenericConfirmModal(
            'Confirm Sync', 
            'This will synchronize payment records for this order. This action attempts to align payment history and verification statuses. Continue?',
            'warning',
            () => { // onConfirm
                fetch('/hm/api/orders/sync_payment_records.php?order_id=' + orderId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let message = 'Payment records synchronized successfully.';
                            if (data.no_changes) {
                                message = 'Payment records are already in sync. No changes were made.';
                            } else if (data.fixed_issues && data.fixed_issues.length > 0) {
                                message = 'Payment records synchronized. Fixed ' + data.fixed_issues.length + ' issues. The page will now reload.';
                            } else {
                                message = 'Payment records synchronized. The page will now reload.';
                            }
                            showGenericAlertModal('Sync Result', message, data.no_changes ? 'info' : 'success', () => {
                                if (!data.no_changes) window.location.reload();
                            });
                        } else {
                            showGenericAlertModal('Sync Error', data.message || 'Failed to synchronize payment records.', 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showGenericAlertModal('Sync Error', 'An error occurred while synchronizing payment records.', 'danger');
                });
            }
        );
    }

    // Generic Confirmation Modal
    function showGenericConfirmModal(title, bodyText, type = 'primary', onConfirm, onCancel) {
        $('#genericConfirmModal').remove(); // Remove previous modal
        const modalHtml = `
        <div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-labelledby="genericConfirmLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-${type}-subtle">
                <h5 class="modal-title" id="genericConfirmLabel">
                    <i class="bi bi-${type === 'danger' ? 'exclamation-triangle-fill text-danger' : (type === 'warning' ? 'exclamation-triangle-fill text-warning' : 'question-circle-fill text-primary')} me-2"></i>
                    ${title}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                ${bodyText}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="genericConfirmCancelBtn">Cancel</button>
                <button type="button" class="btn btn-${type}" id="genericConfirmOkBtn">Yes, Proceed</button>
              </div>
            </div>
          </div>
        </div>`;
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('genericConfirmModal'));
        
        $('#genericConfirmOkBtn').on('click', function() {
            if (onConfirm) onConfirm();
            modal.hide();
        });
        $('#genericConfirmCancelBtn').on('click', function() {
            if (onCancel) onCancel();
        });
         $('#genericConfirmModal').on('hidden.bs.modal', function () {
            // if (onCancel && !confirmed) onCancel(); // Be careful with multiple onCancel calls
            $(this).remove();
        });
        modal.show();
    }

    // Generic Alert Modal
    function showGenericAlertModal(title, bodyText, type = 'info', onHidden) {
        $('#genericAlertModal').remove(); // Remove previous modal
        const modalHtml = `
        <div class="modal fade" id="genericAlertModal" tabindex="-1" aria-labelledby="genericAlertLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-${type}-subtle">
                <h5 class="modal-title" id="genericAlertLabel">
                    <i class="bi bi-${type === 'danger' ? 'x-octagon-fill text-danger' : (type === 'warning' ? 'exclamation-triangle-fill text-warning' : (type === 'success' ? 'check-circle-fill text-success' : 'info-circle-fill text-info'))} me-2"></i>
                    ${title}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                ${bodyText}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
              </div>
            </div>
          </div>
        </div>`;
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('genericAlertModal'));
        $('#genericAlertModal').on('hidden.bs.modal', function () {
            if (onHidden) onHidden();
            $(this).remove();
        });
        modal.show();
    }

    // Function to verify COD payment
    function verifyCODPayment(orderId, verify = true) {
        if (!verify) {
            // If we're unsetting verification, confirm first
            showGenericConfirmModal(
                'Reset Payment Verification', 
                'Are you sure you want to reset the Cash on Delivery payment verification? This will mark the payment as not received.',
                'warning',
                () => {
                    submitCODVerification(orderId, false);
                }
            );
            return;
        }
        
        // Get form values
        const receiptNumber = $('#codReceiptNumber').val().trim();
        const amountCollected = parseFloat($('#codAmountCollected').val());
        const verificationNotes = $('#codVerificationNotes').val().trim();
        
        // Validate inputs
        if (!receiptNumber) {
            showToast('warning', 'Please enter a receipt/reference number');
            return;
        }
        
        if (!amountCollected || amountCollected <= 0) {
            showToast('warning', 'Please enter a valid amount collected');
            return;
        }
        
        // Prepare verification data
        const verificationData = {
            order_id: orderId,
            verify: true,
            payment_receipt_number: receiptNumber,
            collected_amount: amountCollected,
            verification_notes: verificationNotes || 'Cash on Delivery payment verified by admin'
        };
        
        // Submit the data
        fetch('/hm/admin/api/orders/verify_cod_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(verificationData),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast('success', 'Cash on Delivery payment verified successfully');
                
                // Reload the page after a short delay to refresh the order status
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('error', data.message || 'Failed to verify payment');
            }
        })
        .catch(error => {
            console.error('Error verifying payment:', error);
            showToast('error', 'An error occurred while verifying payment');
        });
    }

    // Function to submit COD verification when resetting
    function submitCODVerification(orderId, verify) {
        // Prepare verification data for resetting
        const verificationData = {
            order_id: orderId,
            verify: verify,
            payment_receipt_number: '',
            collected_amount: 0,
            verification_notes: 'Cash on Delivery payment verification reset by admin'
        };
        
        // Submit the data
        fetch('/hm/admin/api/orders/verify_cod_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(verificationData),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showGenericAlertModal('Success', data.message, 'success', () => {
                    window.location.reload(); // Reload the page to reflect changes
                });
            } else {
                showGenericAlertModal('Error', data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showGenericAlertModal('Error', 'An error occurred while processing your request.', 'danger');
        });
    }

    // Helper function to show toast notifications
    function showToast(type, message) {
        if (typeof toastr !== 'undefined') {
            // Use toastr if it's available
            if (type === 'success') {
                toastr.success(message);
            } else if (type === 'warning') {
                toastr.warning(message);
            } else if (type === 'error') {
                toastr.error(message);
            } else {
                toastr.info(message);
            }
        } else {
            // Fallback to alert
            alert(message);
        }
    }

    // Function to copy account number to clipboard
    function copyAccountNumber() {
        const accountNumber = document.getElementById('company_account');
        
        if (!accountNumber) {
            showToast('error', 'Account number field not found');
            return;
        }
        
        try {
            // Create a temporary input for copying
            const tempInput = document.createElement('input');
            tempInput.value = accountNumber.value;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            // Show success message
            showToast('success', 'Account number copied to clipboard!');
        } catch (err) {
            console.error('Error copying:', err);
            showToast('error', 'Failed to copy account number');
        }
    }
</script>

</body>
</html> 