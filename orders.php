<?php
session_start();
require_once 'config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if we need to reset the cart count in localStorage
$reset_cart_count = false;
if (isset($_SESSION['reset_cart_count']) && $_SESSION['reset_cart_count']) {
    $reset_cart_count = true;
    $_SESSION['reset_cart_count'] = false; // Reset the flag
}

// Check if redirected from checkout with success
$checkout_success = isset($_GET['checkout_success']) && $_GET['checkout_success'] === 'true';
$new_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Fetch user's orders with their items
$stmt = $mysqli->prepare("
    SELECT 
        o.*,
        os.status_name,
        ps.payment_method,
        COALESCE(latest_pv.payment_status, 0) as payment_status,
        COALESCE(latest_pv.payment_verified, 0) as payment_verified,
        COALESCE(latest_pv.transfer_slip, '') as transfer_slip,
        COALESCE(latest_pv.verification_notes, '') as verification_notes,
        COUNT(oi.order_item_id) as items_count,
        SUM(oi.price_per_unit * oi.quantity) as subtotal,
        (SELECT COUNT(*) FROM order_notifications 
         WHERE order_id = o.order_id 
         AND user_id = ? 
         AND is_read = 0) as unread_notifications
    FROM orders o
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
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id, o.user_id, o.status_id, o.created_at, o.updated_at, os.status_name, ps.payment_method, 
             o.delivery_address, o.contact_number, o.customer_phone, o.delivery_notes, o.payment_method_id, 
             o.account_phone, o.delivery_fee, o.delivery_option_id, o.expected_delivery_date, 
             o.preferred_delivery_time, o.subtotal, o.tax, o.total_amount,
             latest_pv.payment_status, latest_pv.payment_verified, latest_pv.transfer_slip, 
             latest_pv.verification_notes, latest_pv.verification_attempt, latest_pv.amount_verified,
             latest_pv.created_at
    ORDER BY o.created_at DESC
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$orders = $stmt->get_result();

// Fetch user data for sidebar
$userStmt = $mysqli->prepare("SELECT username, full_name FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* Dashboard specific styles */
        body {
            background-color: #f8f9fa;
            padding-top: 56px; /* Added space for navbar */
        }
        
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 56px); /* Adjusted for navbar */
        }
        
        .sidebar {
            width: 250px;
            background: #343a40;
            color: #fff;
            position: fixed;
            height: calc(100% - 56px); /* Adjusted for navbar */
            top: 56px; /* Start below navbar */
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 999;
        }
        
        .sidebar .sidebar-header {
            padding: 20px;
            background: #2c3136;
        }
        
        .sidebar ul li a {
            padding: 15px 20px;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: #2c3136;
            border-left-color: #198754;
        }
        
        .sidebar ul li a i {
            margin-right: 10px;
        }
        
        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .content-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            padding: 1rem 1.25rem;
        }
        
        .order-card {
            transition: all 0.2s ease-in-out;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .status-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 50rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                width: 100%;
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 250px;
            }
            
            .overlay {
                display: none;
                position: fixed;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.7);
                z-index: 998;
                opacity: 0;
                transition: all 0.5s ease-in-out;
                top: 56px; /* Start below navbar */
            }
            
            .overlay.active {
                display: block;
                opacity: 1;
            }
        }
        
        .toggle-btn {
            background: #198754;
            color: white;
            position: fixed;
            top: 70px; /* Adjusted for navbar */
            left: 15px;
            z-index: 999;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            display: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        @media (max-width: 768px) {
            .toggle-btn {
                display: block;
            }
        }
        
        .bg-gradient-primary {
            background: linear-gradient(45deg, #4e73df, #224abe);
        }
        .bg-gradient-success {
            background: linear-gradient(45deg, #1cc88a, #13855c);
        }
        .bg-gradient-info {
            background: linear-gradient(45deg, #36b9cc, #258391);
        }
        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="overlay" onclick="toggleSidebar()"></div>

<button class="toggle-btn" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<div class="dashboard-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>My Account</h3>
            <p class="mb-0"><?php echo htmlspecialchars($user['username']); ?></p>
        </div>
        
        <ul class="list-unstyled">
            <li>
                <a href="index.php" class="d-flex align-items-center">
                    <i class="bi bi-house-door"></i>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="d-flex align-items-center">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="orders.php" class="d-flex align-items-center active">
                    <i class="bi bi-bag"></i>
                    <span>My Orders</span>
                </a>
            </li>
            <li>
                <a href="favorites.php" class="d-flex align-items-center">
                    <i class="bi bi-heart"></i>
                    <span>Favorites</span>
                </a>
            </li>
            <!-- <li>
                <a href="meal_plans.php" class="d-flex align-items-center">
                    <i class="bi bi-calendar-check"></i>
                    <span>Meal Plans</span>
                </a>
            </li> -->
            <li>
                <a href="api/auth/logout.php" class="d-flex align-items-center">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h1 class="m-0">My Orders</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-sm-end">
                            <a href="meal-kits.php" class="btn btn-primary">
                                <i class="bi bi-bag-plus"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php 
        // Fetch recent order notifications
        $notification_stmt = $mysqli->prepare("
            SELECT notif.message, notif.created_at, o.order_id, os.status_name 
            FROM order_notifications notif
            JOIN orders o ON notif.order_id = o.order_id
            JOIN order_status os ON o.status_id = os.status_id
            WHERE notif.user_id = ? 
            ORDER BY notif.created_at DESC
            LIMIT 5
        ");
        $notification_stmt->bind_param("i", $_SESSION['user_id']);
        $notification_stmt->execute();
        $recent_notifications = $notification_stmt->get_result();
        
        if ($recent_notifications->num_rows > 0):
        ?>
        <div class="container-fluid mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-bell me-2"></i>Recent Order Updates</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($notification = $recent_notifications->fetch_assoc()): 
                            // Determine icon based on message content
                            $icon = 'bi-info-circle';
                            if (strpos($notification['message'], 'delivered') !== false) {
                                $icon = 'bi-check-circle-fill text-success';
                            } elseif (strpos($notification['message'], 'verified') !== false) {
                                $icon = 'bi-shield-check text-success';
                            } elseif (strpos($notification['message'], 'processing') !== false) {
                                $icon = 'bi-gear text-primary';
                            } elseif (strpos($notification['message'], 'cancelled') !== false) {
                                $icon = 'bi-x-circle text-danger';
                            } elseif (strpos($notification['message'], 'payment') !== false) {
                                $icon = 'bi-credit-card text-info';
                            }
                        ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $icon; ?> fs-4 me-3"></i>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></h6>
                                        <div class="d-flex align-items-center">
                                            <span class="badge 
                                                <?php echo match($notification['status_name']) {
                                                    'Pending' => 'bg-warning text-dark',
                                                    'Processing' => 'bg-info text-dark',
                                                    'Shipped' => 'bg-primary',
                                                    'Delivered' => 'bg-success',
                                                    'Cancelled' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                }; ?>
                                                me-2"><?php echo htmlspecialchars($notification['status_name']); ?></span>
                                            <small class="text-muted">Order #<?php echo $notification['order_id']; ?></small>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo date('M d, g:i A', strtotime($notification['created_at'])); ?></small>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="card-footer bg-light py-2 text-end">
                        <a href="#" onclick="document.getElementById('orderSearch').focus()" class="link-primary text-decoration-none">
                            <i class="bi bi-search me-1"></i>Find My Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($checkout_success && $new_order_id > 0): ?>
        <div class="container-fluid mb-4">
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-success bg-opacity-25 me-3">
                        <i class="bi bi-check-circle-fill fs-3 text-success"></i>
                    </div>
                    <div>
                        <h4 class="alert-heading mb-1">Order Placed Successfully!</h4>
                        <p class="mb-1">Your order #<?php echo $new_order_id; ?> has been received and is being processed. You can track its status below.</p>
                        <?php if ($orders->num_rows > 0): $orders->data_seek(0); $latest_order = $orders->fetch_assoc(); ?>
                            <?php if ($latest_order['payment_method'] != 'Cash on Delivery'): ?>
                                <?php if ($latest_order['payment_status'] == 4): ?>
                                    <div class="mt-2">
                                        <strong>Note:</strong> Partial payment received. Please complete the remaining payment.
                                    </div>
                                <?php elseif ($latest_order['payment_verified'] == 0): ?>
                                    <div class="mt-2">
                                        <strong>Note:</strong> Your payment is being verified. You'll be notified once it's confirmed.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        
        <script>
            // Highlight the newly placed order
            document.addEventListener('DOMContentLoaded', function() {
                const newOrderRow = document.querySelector(`tr.order-item[data-order-id="${<?php echo $new_order_id; ?>}"]`);
                if (newOrderRow) {
                    newOrderRow.classList.add('table-success');
                    newOrderRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Pulse animation effect
                    setTimeout(() => {
                        newOrderRow.style.transition = 'all 0.5s ease-in-out';
                        newOrderRow.style.boxShadow = '0 0 10px 5px rgba(25, 135, 84, 0.3)';
                        
                        setTimeout(() => {
                            newOrderRow.style.boxShadow = 'none';
                        }, 1500);
                    }, 500);
                }
            });
        </script>
        <?php endif; ?>
        
        <div class="row mb-4">
            <!-- Order Statistics -->
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm bg-gradient-primary text-white">
                    <div class="card-body py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2">Total Orders</h6>
                                <h2 class="display-4 mb-0"><?php echo $orders->num_rows; ?></h2>
                            </div>
                            <div class="icon-circle bg-white bg-opacity-25">
                                <i class="bi bi-bag-check fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm bg-gradient-success text-white">
                    <div class="card-body py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2">Active Orders</h6>
                                <?php
                                $pendingCount = 0;
                                $orders->data_seek(0);
                                while ($order = $orders->fetch_assoc()) {
                                    if ($order['status_name'] == 'Pending' || $order['status_name'] == 'Processing') {
                                        $pendingCount++;
                                    }
                                }
                                $orders->data_seek(0);
                                ?>
                                <h2 class="display-4 mb-0"><?php echo $pendingCount; ?></h2>
                            </div>
                            <div class="icon-circle bg-white bg-opacity-25">
                                <i class="bi bi-clock-history fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm bg-gradient-info text-white">
                    <div class="card-body py-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-2">Total Spent</h6>
                                <?php
                                $totalSpent = 0;
                                $orders->data_seek(0);
                                while ($order = $orders->fetch_assoc()) {
                                    $totalSpent += $order['total_amount'];
                                }
                                $orders->data_seek(0);
                                ?>
                                <h2 class="display-4 mb-0"><?php echo number_format($totalSpent); ?> MMK</h2>
                            </div>
                            <div class="icon-circle bg-white bg-opacity-25">
                                <i class="bi bi-wallet2 fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Search & Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5 mb-3 mb-md-0">
                        <input type="text" id="orderSearch" class="form-control" placeholder="Search orders...">
                    </div>
                    <div class="col-md-5 mb-3 mb-md-0">
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="needs_verification">Needs Payment Verification</option>
                            <option value="payment_failed">Payment Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="resetFilters" class="btn btn-secondary w-100">Reset</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Orders Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Order History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="ordersTable">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr class="order-item" data-order-id="<?php echo $order['order_id']; ?>" 
                                data-status="<?php echo $order['status_name']; ?>"
                                data-payment-method="<?php echo $order['payment_method']; ?>"
                                data-needs-verification="<?php echo (!empty($order['transfer_slip']) && $order['payment_verified'] == 0 && $order['payment_method'] != 'Cash on Delivery') ? '1' : '0'; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <strong>#<?php echo $order['order_id']; ?></strong>
                                        <?php if ($order['unread_notifications'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill ms-2" title="<?php echo $order['unread_notifications']; ?> unread updates">
                                            <?php echo $order['unread_notifications']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td><?php echo $order['items_count']; ?> items</td>
                                <td>
                                    <?php
                                    $statusClass = match($order['status_name']) {
                                        'Pending' => 'bg-warning text-dark',
                                        'Processing' => 'bg-info text-dark',
                                        'Shipped' => 'bg-primary',
                                        'Delivered' => 'bg-success',
                                        'Cancelled' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $order['status_name']; ?></span>
                                </td>
                                <td>
                                    <?php if ($order['payment_method'] != 'Cash on Delivery'): ?>
                                        <?php if ($order['payment_status'] == 3): ?>
                                            <span class="badge bg-info" 
                                                  title="<?php echo !empty($order['verification_notes']) ? htmlspecialchars($order['verification_notes']) : 'Payment has been refunded'; ?>">
                                                  Refunded
                                            </span>
                                        <?php elseif ($order['payment_status'] == 2): ?>
                                            <span class="badge bg-danger">Payment Failed</span>
                                            <a href="#" class="small d-block mt-1 text-danger fw-bold" 
                                               onclick="viewOrderDetails(<?php echo $order['order_id']; ?>); return false;"
                                               title="<?php echo !empty($order['verification_notes']) ? htmlspecialchars($order['verification_notes']) : 'Payment verification failed. Please resubmit.'; ?>">
                                                <i class="bi bi-exclamation-triangle"></i> Action Required
                                                <?php if (!empty($order['verification_notes'])): ?>
                                                <span class="d-none"><?php echo htmlspecialchars($order['verification_notes']); ?></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php elseif ($order['payment_status'] == 4): ?>
                                            <span class="badge bg-warning text-dark">Partial Payment</span>
                                            <a href="#" class="small d-block mt-1 text-warning fw-bold" 
                                               onclick="viewOrderDetails(<?php echo $order['order_id']; ?>); return false;">
                                                <i class="bi bi-info-circle"></i> View Details
                                            </a>
                                        <?php elseif ($order['payment_verified'] == 1): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending Verification</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-info">COD</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($order['total_amount']); ?> MMK</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <?php if ($order['status_name'] == 'Delivered'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                            onclick="reorderItems(<?php echo $order['order_id']; ?>)">
                                        <i class="bi bi-cart-plus"></i> Reorder
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($order['status_name'] == 'Pending' || $order['status_name'] == 'Processing'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                        <i class="bi bi-trash"></i> Cancel
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Order Confirmation Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="cancelOrderModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Cancel Order?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <i class="bi bi-x-circle display-3 text-warning mb-3"></i>
        <p class="fs-5">Are you sure you want to cancel this order? This action cannot be undone.</p>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
        <button type="button" class="btn btn-warning text-dark fw-bold" id="confirmCancelOrderBtn"><i class="bi bi-x-lg me-1"></i>Cancel Order</button>
      </div>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/toast-notifications.php'; ?>

<!-- jQuery first, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- Search functionality -->
<script src="assets/js/search.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reset cart count in localStorage if needed
    <?php if ($reset_cart_count): ?>
    localStorage.setItem('cartCount', '0');
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = '0';
    }
    <?php endif; ?>
    
    // Initialize DataTable
    if ($.fn.DataTable.isDataTable('#ordersTable')) {
        $('#ordersTable').DataTable().destroy();
    }
    
    // Check for success parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        const message = urlParams.get('message') || 'Your order has been placed successfully!';
        
        // Show success toast
        const successToast = document.getElementById('successToast');
        if (successToast) {
            const toastBody = successToast.querySelector('.toast-body');
            if (toastBody) {
                toastBody.innerHTML = `<i class="bi bi-check-circle me-2"></i> ${message}`;
            }
            const bsToast = new bootstrap.Toast(successToast);
            bsToast.show();
        }
        
        // Remove success parameter from URL without refreshing the page
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Initialize DataTable
    const table = $('#ordersTable').DataTable({
        order: [[1, 'desc']], // Sort by date by default
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        language: {
            search: "",
            searchPlaceholder: "Search in table...",
            lengthMenu: "Show _MENU_ orders per page",
            info: "Showing _START_ to _END_ of _TOTAL_ orders",
            infoEmpty: "No orders found",
            zeroRecords: "No matching orders found",
            emptyTable: "No orders available"
        },
        dom: 'rtilp', // Hide the default search box
        responsive: true
    });
    
    // Custom search
    $('#orderSearch').on('keyup', function() {
        table.search(this.value).draw();
    });
    
    // Status filter
    $('#statusFilter').on('change', function() {
        const status = this.value;
        
        // Clear any existing custom search filter
        $.fn.dataTable.ext.search.pop();
        
        if (status === 'all') {
            table.search('').draw();
        } else if (status === 'needs_verification') {
            // Custom filtering for orders that need verification
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const row = table.row(dataIndex).node();
                    return $(row).attr('data-needs-verification') === '1';
                }
            );
            table.draw();
        } else if (status === 'payment_failed') {
            // Custom filtering for orders with failed payment
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const badge = $(table.row(dataIndex).node()).find('td:nth-child(5) .badge.bg-danger');
                    return badge.length > 0 && badge.text() === 'Payment Failed';
                }
            );
            table.draw();
        } else {
            // Filter by order status
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const row = table.row(dataIndex).node();
                    return $(row).attr('data-status') === status;
                }
            );
            table.draw();
        }
    });
    
    // Reset filters
    $('#resetFilters').on('click', function() {
        $('#orderSearch').val('');
        $('#statusFilter').val('all');
        // Clear any custom search filters
        $.fn.dataTable.ext.search.pop();
        table.search('').draw();
    });
});

// Toggle Sidebar
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    document.querySelector('.main-content').classList.toggle('active');
    document.querySelector('.overlay').classList.toggle('active');
}

// View Order Details
function viewOrderDetails(orderId) {
    fetch(`api/orders/get_details.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('orderDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                modal.show();
                
                // Attach event listener to payment submit button if it exists
                const submitBtn = document.getElementById('orderDetailsContent').querySelector('.orderDetailsSubmitBtn');
                if (submitBtn) {
                    // Set up file upload preview functionality
                    const fileInput = document.getElementById('transferSlip');
                    const fileSelectionText = document.getElementById('fileSelectionText');
                    const previewContainer = document.getElementById('previewContainer');
                    const imagePreview = document.getElementById('imagePreview');
                    const pdfPreview = document.getElementById('pdfPreview');
                    const previewImg = document.getElementById('previewImg');
                    const removeFileBtn = document.getElementById('removeFileBtn');
                    const viewImageBtn = document.getElementById('viewImageBtn');
                    const uploadContainer = document.querySelector('.upload-container');
                    
                    // Initialize file preview functionality
                    if (fileInput) {
                        fileInput.addEventListener('change', function() {
                            const file = this.files[0];
                            
                            if (file) {
                                // Hide error alert if visible
                                const errorDiv = document.getElementById('uploadError');
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
                    
                    // Initialize remove button functionality
                    if (removeFileBtn) {
                        removeFileBtn.addEventListener('click', function() {
                            fileInput.value = '';
                            previewContainer.classList.add('d-none');
                            fileSelectionText.textContent = 'Click to select payment slip image or PDF';
                            uploadContainer.style.borderColor = 'rgba(0, 123, 255, 0.3)';
                            uploadContainer.style.background = 'rgba(0, 123, 255, 0.03)';
                        });
                    }
                    
                    // Initialize view image button
                    if (viewImageBtn) {
                        viewImageBtn.addEventListener('click', function() {
                            if (previewImg && previewImg.src) {
                                createImagePreviewModal(previewImg.src);
                            }
                        });
                    }
                    
                    // Initialize existing view image buttons
                    document.querySelectorAll('.view-image-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const imgSrc = this.getAttribute('data-img-src');
                            if (imgSrc) {
                                createImagePreviewModal(imgSrc);
                            }
                        });
                    });
                    
                    // Create image preview modal function
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
                    
                    // Submit button click event
                    submitBtn.addEventListener('click', function() {
                        // Get the form and file input
                        const form = document.getElementById('paymentSlipForm');
                        const fileInput = document.getElementById('transferSlip');
                        const errorDiv = document.getElementById('uploadError');
                        const successDiv = document.getElementById('uploadSuccess');
                        
                        // Validate form and show error if needed
                        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                            if (errorDiv) {
                                const errorMessage = errorDiv.querySelector('#errorMessage');
                                if (errorMessage) errorMessage.textContent = 'Please select a file before submitting.';
                                
                                errorDiv.classList.remove('d-none');
                                errorDiv.classList.add('animate__animated', 'animate__shakeX');
                                
                                // Highlight upload container
                                const uploadContainer = document.querySelector('.upload-container');
                                if (uploadContainer) {
                                    uploadContainer.classList.add('animate__animated', 'animate__headShake');
                                    uploadContainer.style.borderColor = 'rgba(220, 53, 69, 0.5)';
                                    uploadContainer.style.background = 'rgba(220, 53, 69, 0.05)';
                                }
                                
                                // Scroll to error message
                                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                
                                // Automatically open file dialog after a short delay
                                setTimeout(function() {
                                    // Reset upload container style
                                    if (uploadContainer) {
                                        uploadContainer.style.borderColor = 'rgba(0, 123, 255, 0.3)';
                                        uploadContainer.style.background = 'rgba(0, 123, 255, 0.03)';
                                    }
                                    
                                    // Trigger click on the file input to open file dialog
                                    fileInput.click();
                                }, 1000);
                            } else {
                                // Scroll to error message
                                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                
                                // Automatically open file dialog after a short delay
                                setTimeout(function() {
                                    // Reset upload container style
                                    uploadContainer.style.borderColor = 'rgba(0, 123, 255, 0.3)';
                                    uploadContainer.style.background = 'rgba(0, 123, 255, 0.03)';
                                    
                                    // Trigger click on the file input to open file dialog
                                    fileInput.click();
                                }, 1000);
                            }
                        }
                        
                        // Get form data
                        const formData = new FormData(form);
                        
                        // Show loading state
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading...';
                        
                        // Submit form with fetch
                        fetch("api/orders/resubmit_payment.php", {
                            method: "POST",
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Reset button state
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            
                            if (data.success) {
                                // Show custom success message if available
                                if (successDiv) {
                                    const successMessage = successDiv.querySelector('#successMessage');
                                    if (successMessage) successMessage.textContent = data.message || 'Payment slip uploaded successfully.';
                                    
                                    successDiv.classList.remove('d-none');
                                    successDiv.classList.add('animate__animated', 'animate__fadeIn');
                                    
                                    // Scroll to success message
                                    successDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    
                                    // Reset form
                                    const removeBtn = document.getElementById('removeFileBtn');
                                    if (removeBtn) removeBtn.click();
                                } else {
                                    // Fallback to toast
                                    const successToast = document.getElementById('successToast');
                                    if (successToast) {
                                        const toastBody = successToast.querySelector('.toast-body');
                                        if (toastBody) {
                                            toastBody.innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>' + data.message;
                                        }
                                        const toast = new bootstrap.Toast(successToast);
                                        toast.show();
                                    }
                                }
                                
                                // Close the modal and reload the page after a delay
                                setTimeout(() => {
                                    const orderModal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
                                    if (orderModal) {
                                        orderModal.hide();
                                    }
                                    location.reload();
                                }, 2000);
                            } else {
                                // Show custom error message if available
                                if (errorDiv) {
                                    const errorMessage = errorDiv.querySelector('#errorMessage');
                                    if (errorMessage) errorMessage.textContent = data.message || 'Failed to upload payment slip.';
                                    
                                    errorDiv.classList.remove('d-none');
                                    errorDiv.classList.add('animate__animated', 'animate__shakeX');
                                    
                                    // Scroll to error message
                                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                } else {
                                    // Fallback to toast
                                    const errorToast = document.getElementById('errorToast');
                                    if (errorToast) {
                                        const toastBody = errorToast.querySelector('.toast-body');
                                        if (toastBody) {
                                            toastBody.innerHTML = '<i class="bi bi-exclamation-circle text-danger me-2"></i>' + data.message;
                                        }
                                        const toast = new bootstrap.Toast(errorToast);
                                        toast.show();
                                    }
                                }
                            }
                        })
                        .catch(error => {
                            // Reset button state
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            
                            console.error('Error:', error);
                            // Show error message
                            if (errorDiv) {
                                const errorMessage = errorDiv.querySelector('#errorMessage');
                                if (errorMessage) errorMessage.textContent = 'A network error occurred. Please try again.';
                                
                                errorDiv.classList.remove('d-none');
                                errorDiv.classList.add('animate__animated', 'animate__shakeX');
                                
                                // Scroll to error message
                                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            } else {
                                const errorToast = document.getElementById('errorToast');
                                if (errorToast) {
                                    const toastBody = errorToast.querySelector('.toast-body');
                                    if (toastBody) {
                                        toastBody.innerHTML = '<i class="bi bi-exclamation-circle text-danger me-2"></i>A network error occurred. Please try again.';
                                    }
                                    const toast = new bootstrap.Toast(errorToast);
                                    toast.show();
                                }
                            }
                        });
                    });
                }
                
                // Update notification badge (remove it when order details are viewed)
                const orderRow = document.querySelector(`tr.order-item[data-order-id="${orderId}"]`);
                if (orderRow) {
                    const badge = orderRow.querySelector('.badge.bg-danger');
                    if (badge) {
                        badge.remove();
                    }
                }
            } else {
                // Show error in toast
                const errorToast = document.getElementById('errorToast');
                if (errorToast) {
                    document.getElementById('errorToastMessage').textContent = data.message || 'Error fetching order details';
                    const toast = new bootstrap.Toast(errorToast);
                    toast.show();
                } else {
                    alert(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorToast = document.getElementById('errorToast');
            if (errorToast) {
                document.getElementById('errorToastMessage').textContent = 'An error occurred while fetching order details';
                const toast = new bootstrap.Toast(errorToast);
                toast.show();
            } else {
                alert('An error occurred while fetching order details');
            }
        });
}

let cancelOrderId = null;
let reopenOrderDetails = false;
function cancelOrder(orderId) {
    cancelOrderId = orderId;
    // If order details modal is open, hide it and remember to reopen
    const orderDetailsModalEl = document.getElementById('orderDetailsModal');
    const orderDetailsModal = bootstrap.Modal.getInstance(orderDetailsModalEl);
    if (orderDetailsModal && orderDetailsModalEl.classList.contains('show')) {
        orderDetailsModal.hide();
        reopenOrderDetails = true;
    } else {
        reopenOrderDetails = false;
    }
    const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    modal.show();
}
document.addEventListener('DOMContentLoaded', function() {
    // Restore order details modal if needed after cancel modal closes
    const cancelOrderModal = document.getElementById('cancelOrderModal');
    cancelOrderModal.addEventListener('hidden.bs.modal', function () {
        // Remove all but the last modal-backdrop
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length > 1) {
            for (let i = 0; i < backdrops.length - 1; i++) {
                backdrops[i].parentNode.removeChild(backdrops[i]);
            }
        }
        if (reopenOrderDetails) {
            const orderDetailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            orderDetailsModal.show();
            reopenOrderDetails = false;
        }
    });
    const confirmBtn = document.getElementById('confirmCancelOrderBtn');
    if (confirmBtn) {
        confirmBtn.onclick = function() {
            if (!cancelOrderId) return;
            fetch('api/orders/cancel_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: cancelOrderId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    document.getElementById('errorToastMessage').textContent = data.message || 'Error cancelling order';
                    const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                    toast.show();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('errorToastMessage').textContent = 'Error cancelling order. Please try again.';
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
            });
            cancelOrderId = null;
        }
    }
});

// Add uploadPaymentSlip function to handle payment slip uploads
function uploadPaymentSlip() {
    const form = document.getElementById("paymentSlipForm");
    if (!form) {
        console.error("Payment slip form not found");
        return;
    }
    
    const formData = new FormData(form);
    const fileInput = document.getElementById("transferSlip");
    
    // Validate file
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert("Please select a file to upload");
        return;
    }
    
    // Show loading state
    const uploadBtn = form.querySelector("button[type='button']");
    const originalText = uploadBtn.innerHTML;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Uploading...';
    
    fetch("api/orders/upload_payment_slip.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Reset button state
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
        
        if (data.success) {
            // Show success message
            const successToast = document.getElementById("successToast");
            if (successToast) {
                const toastBody = successToast.querySelector(".toast-body");
                if (toastBody) {
                    toastBody.innerHTML = '<i class="bi bi-check-circle text-success me-2"></i>' + data.message;
                }
                const toast = new bootstrap.Toast(successToast);
                toast.show();
            } else {
                alert("Success: " + data.message);
            }
            
            // Close the modal and reload the page after a delay
            setTimeout(() => {
                const orderModal = bootstrap.Modal.getInstance(document.getElementById("orderDetailsModal"));
                if (orderModal) {
                    orderModal.hide();
                }
                location.reload();
            }, 2000);
        } else {
            // Show error message
            const errorToast = document.getElementById("errorToast");
            if (errorToast) {
                const toastBody = errorToast.querySelector(".toast-body");
                if (toastBody) {
                    toastBody.innerHTML = '<i class="bi bi-exclamation-circle text-danger me-2"></i>' + data.message;
                }
                const toast = new bootstrap.Toast(errorToast);
                toast.show();
            } else {
                alert("Error: " + data.message);
            }
        }
    })
    .catch(error => {
        // Reset button state
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = originalText;
        
        console.error("Error:", error);
        alert("An error occurred while uploading the payment slip. Please try again.");
    });
}

</script>

<script src="assets/js/main.js"></script>

</body>
</html>