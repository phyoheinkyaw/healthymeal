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

// Fetch user's orders with their items
$stmt = $mysqli->prepare("
    SELECT o.*, 
           os.status_name,
           COUNT(oi.order_item_id) as total_items,
           SUM(oi.quantity * oi.price_per_unit) as total_amount
    FROM orders o
    LEFT JOIN order_status os ON o.status_id = os.status_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
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
            <li>
                <a href="meal_plans.php" class="d-flex align-items-center">
                    <i class="bi bi-calendar-check"></i>
                    <span>Meal Plans</span>
                </a>
            </li>
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
            <h1>My Orders</h1>
            <p class="text-muted">View and track your order history</p>
        </div>
        
        <div class="row mb-4">
            <!-- Order Statistics -->
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white text-center">
                    <div class="card-body py-4">
                        <h2 class="display-4"><?php echo $orders->num_rows; ?></h2>
                        <p class="mb-0">Total Orders</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white text-center">
                    <div class="card-body py-4">
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
                        <h2 class="display-4"><?php echo $pendingCount; ?></h2>
                        <p class="mb-0">Active Orders</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card bg-info text-white text-center">
                    <div class="card-body py-4">
                        <h2 class="display-4">
                        <?php
                        $totalSpent = 0;
                        $orders->data_seek(0);
                        while ($order = $orders->fetch_assoc()) {
                            $totalSpent += $order['total_amount'] + $order['delivery_fee'];
                        }
                        $orders->data_seek(0);
                        echo '$' . number_format($totalSpent, 2);
                        ?>
                        </h2>
                        <p class="mb-0">Total Spent</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Search & Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <input type="text" id="orderSearch" class="form-control" placeholder="Search orders...">
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Processing">Processing</option>
                            <option value="Shipped">Shipped</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
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
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr class="order-item" data-status="<?php echo $order['status_name']; ?>">
                                <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                <td>
                                    <div><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td><?php echo $order['total_items']; ?> items</td>
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
                                <td>$<?php echo number_format($order['total_amount'] + $order['delivery_fee'], 2); ?></td>
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

<?php include 'includes/footer.php'; ?>
<?php include 'includes/toast-notifications.php'; ?>

<!-- Bootstrap & jQuery JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

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
        
        if (status === 'all') {
            table.column(3).search('').draw();
        } else {
            table.column(3).search(status).draw();
        }
    });
    
    // Reset filters
    $('#resetFilters').on('click', function() {
        $('#orderSearch').val('');
        $('#statusFilter').val('all');
        table.search('').columns().search('').draw();
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
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while fetching order details');
        });
}

// Cancel Order
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        fetch('api/orders/cancel_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload(); // Reload the page to update order status
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cancelling the order');
        });
    }
}

// Reorder Items
function reorderItems(orderId) {
    fetch(`api/orders/reorder.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count in both DOM and localStorage
                const cartCount = data.total_items || 0;
                const cartCountElement = document.getElementById('cartCount');
                if (cartCountElement) {
                    cartCountElement.textContent = cartCount;
                }
                localStorage.setItem('cartCount', cartCount);
                
                // Show message
                alert(data.message);
                
                // Only redirect to cart page if items were actually added
                if (cartCount > 0) {
                    window.location.href = 'cart.php';
                }
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding items to cart');
        });
}
</script>

</body>
</html>