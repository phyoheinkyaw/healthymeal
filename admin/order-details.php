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
        ph.payment_id,
        ph.transaction_id,
        ps.payment_method,
        ps.account_phone as company_account_phone,
        COALESCE(pv.payment_status, 0) as payment_status
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_status os ON o.status_id = os.status_id
    LEFT JOIN payment_history ph ON o.order_id = ph.order_id
    LEFT JOIN payment_verifications pv ON o.order_id = pv.order_id
    LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
    WHERE o.order_id = ?
");

if (!$stmt) {
    die('SQL Error: ' . $mysqli->error);
}

$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: /hm/admin/orders.php");
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

// Get payment status
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
        .order-item-price {
            text-align: right;
            min-width: 100px;
        }
        .order-item-quantity {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .order-item-total {
            font-weight: 600;
            font-size: 1.125rem;
            color: #343a40;
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
            .order-item-price {
                text-align: left;
                margin-top: 1rem;
            }
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
                                        </div>
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
                        
                        <?php if (!empty($order['transaction_id'])): ?>
                        <div class="info-group">
                            <div class="info-label">Transaction ID</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['transaction_id']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($order['transfer_slip'])): ?>
                        <div class="info-group">
                            <div class="info-label">Payment Slip</div>
                            <a href="<?php echo get_absolute_file_path($order['transfer_slip']); ?>" target="_blank">
                                <img src="<?php echo get_absolute_file_path($order['transfer_slip']); ?>" class="payment-slip" alt="Payment Slip">
                            </a>
                            
                            <?php if ($order['payment_verified'] == 1): ?>
                            <div class="payment-verified-badge">
                                <i class="bi bi-check-circle-fill"></i> Payment Verified
                                <?php if (!empty($order['payment_verified_at'])): ?>
                                    on <?php echo date('F d, Y h:i A', strtotime($order['payment_verified_at'])); ?>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="mt-3">
                                <button type="button" class="btn btn-success" onclick="verifyPayment(<?php echo $order['order_id']; ?>, false)">
                                    <i class="bi bi-shield-check me-2"></i> Verify Payment
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($verificationDetails): ?>
                        <div class="verification-details">
                            <h6 class="mb-3">Verification Details</h6>
                            <div class="verification-row">
                                <div class="info-label">Verified By</div>
                                <div class="info-value"><?php echo htmlspecialchars($verificationDetails['admin_name']); ?></div>
                            </div>
                            <div class="verification-row">
                                <div class="info-label">Transaction ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($verificationDetails['transaction_id']); ?></div>
                            </div>
                            <div class="verification-row">
                                <div class="info-label">Amount Verified</div>
                                <div class="info-value"><?php echo number_format($verificationDetails['amount_verified'], 0); ?> MMK</div>
                            </div>
                            <?php if (!empty($verificationDetails['verification_notes'])): ?>
                            <div class="verification-row">
                                <div class="info-label">Notes</div>
                                <div class="info-value"><?php echo htmlspecialchars($verificationDetails['verification_notes']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($order['payment_method'] !== 'Cash on Delivery'): ?>
                        <div class="payment-actions mt-3">
                            <?php if ($paymentStatus !== 1): // Not verified ?>
                            <button type="button" class="btn btn-primary" onclick="showPaymentVerificationModal(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-shield-check me-2"></i>Verify Payment
                            </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-secondary" onclick="showPaymentHistoryModal(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-clock-history me-2"></i>Verification History
                            </button>
                            
                            <button type="button" class="btn btn-outline-info" onclick="syncPaymentRecords(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-arrow-repeat me-2"></i>Sync Payment Records
                            </button>
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
                    
                    <div class="order-item-price">
                        <?php echo number_format($item['price_per_unit'], 0); ?> MMK × <?php echo $item['quantity']; ?>
                        </div>
                        <div class="order-item-total">
                        <?php echo number_format($item['price_per_unit'] * $item['quantity'], 0); ?> MMK
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
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0 p-4" style="background: #f8f9fa;">
                <div class="d-flex align-items-center">
                    <i class="bi bi-shield-check fs-4 text-success me-2"></i>
                    <h5 class="modal-title fs-4 fw-bold m-0" id="paymentVerificationModalLabel">Verify Payment</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="paymentSlipPreview" class="text-center mb-4 p-3 bg-light rounded-3">
                    <!-- Payment slip image will be shown here -->
                </div>
                <form id="verificationForm" class="bg-white p-4 rounded-3">
                    <input type="hidden" id="verify_order_id" name="order_id" value="">
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="account_number" class="form-label fw-semibold">Customer Account</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control bg-light" id="account_number" name="account_number" readonly>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span>Our KBZPay Account</span>
                                <span class="badge bg-success ms-2 rounded-pill">For Verification</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success-subtle"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control bg-success-subtle text-success fw-bold" id="company_account" value="" readonly>
                                <button class="btn btn-success" type="button" onclick="copyAccountNumber()">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <div class="form-text small mt-1">Compare with the account number on the slip.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_status" class="form-label fw-semibold">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status">
                            <option value="1">Completed</option>
                            <option value="2">Failed</option>
                            <option value="3">Refunded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label fw-semibold">Transaction ID</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                   placeholder="Enter transaction ID from the slip" required>
                            <button class="btn btn-primary" type="button" id="scanTransactionBtn" title="Scan Payment Slip">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                        <div id="transaction_scan_status" class="small text-muted mt-1"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount_verified" class="form-label fw-semibold">Amount Verified</label>
                        <div class="input-group">
                            <span class="input-group-text">MMK</span>
                            <input type="number" class="form-control" id="amount_verified" name="amount_verified" 
                                   value="" step="1" required>
                        </div>
                        <div class="form-text small mt-1">Amount is pre-filled from order but can be modified if needed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control" id="verification_notes" name="verification_notes" 
                                  rows="3" placeholder="Add any verification notes here..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" onclick="submitPaymentVerification()">
                    <i class="bi bi-shield-check me-2"></i>Verify Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Verification History Modal -->
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
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>
<script src="assets/js/orders.js"></script>
<script>
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
        if (!confirm('This will synchronize payment records for this order. Continue?')) {
            return;
        }
        
        fetch('/hm/api/orders/sync_payment_records.php?order_id=' + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.no_changes) {
                        alert('Payment records are already in sync.');
                    } else {
                        alert('Payment records synchronized successfully. Fixed ' + data.fixed_issues.length + ' issues.');
                        // Reload the page to show updated data
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to synchronize payment records'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while synchronizing payment records.');
        });
    }
</script>

</body>
</html> 