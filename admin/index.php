<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Get admin data
$admin_id = $_SESSION['user_id'];
$result = $mysqli->query("SELECT full_name FROM users WHERE user_id = $admin_id");
$admin = $result->fetch_assoc();

// Get counts for dashboard
$users_count = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$orders_count = $mysqli->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$meal_kits_count = $mysqli->query("SELECT COUNT(*) as count FROM meal_kits")->fetch_assoc()['count'];
$ingredients_count = $mysqli->query("SELECT COUNT(*) as count FROM ingredients")->fetch_assoc()['count'];

// Get recent orders
$recent_orders = [];
$orders_result = $mysqli->query("
    SELECT 
        o.order_id,
        u.full_name as customer_name,
        o.created_at,
        os.status_name,
        COUNT(oi.order_item_id) as items_count,
        SUM(oi.price_per_unit * oi.quantity) as total_amount
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_status os ON o.status_id = os.status_id
    JOIN order_items oi ON o.order_id = oi.order_id
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
    LIMIT 5
");

if ($orders_result) {
    while ($row = $orders_result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
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
        <div class="container-fluid">
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card stat-card primary-gradient text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase mb-2">Total Orders</h6>
                            <h2 class="mb-0"><?php echo $orders_count; ?></h2>
                            <div class="icon-background">
                                <i class="bi bi-cart3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card stat-card success-gradient text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase mb-2">Total Users</h6>
                            <h2 class="mb-0"><?php echo $users_count; ?></h2>
                            <div class="icon-background">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card stat-card warning-gradient text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase mb-2">Meal Kits</h6>
                            <h2 class="mb-0"><?php echo $meal_kits_count; ?></h2>
                            <div class="icon-background">
                                <i class="bi bi-box-seam"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-xl-3 mb-4">
                    <div class="card stat-card info-gradient text-white h-100">
                        <div class="card-body">
                            <h6 class="text-uppercase mb-2">Ingredients</h6>
                            <h2 class="mb-0"><?php echo $ingredients_count; ?></h2>
                            <div class="icon-background">
                                <i class="bi bi-egg-fried"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Orders</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="ordersTable">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['order_id']; ?></td>
                                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match(strtolower($order['status_name'])) {
                                                            'pending' => 'warning',
                                                            'processing', 'shipped' => 'info',
                                                            'delivered' => 'success',
                                                            'cancelled' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst($order['status_name']); ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <a href="orders.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($recent_orders)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No recent orders found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>

</body>
</html> 