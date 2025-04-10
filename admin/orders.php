<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Fetch all orders with detailed information
$stmt = $mysqli->prepare("
    SELECT 
        o.order_id,
        u.full_name as customer_name,
        u.email as customer_email,
        o.created_at,
        os.status_id,
        os.status_name,
        o.delivery_address,
        o.contact_number,
        o.delivery_notes,
        o.payment_method,
        o.delivery_fee,
        COUNT(oi.order_item_id) as items_count,
        SUM(oi.price_per_unit * oi.quantity) as subtotal,
        SUM(oi.price_per_unit * oi.quantity) + o.delivery_fee as total_amount
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_status os ON o.status_id = os.status_id
    JOIN order_items oi ON o.order_id = oi.order_id
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");

$stmt->execute();
$orders = $stmt->get_result();

// Fetch order statuses for dropdown
$statuses = [];
$status_result = $mysqli->query("SELECT * FROM order_status ORDER BY status_id");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $statuses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
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
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="page-title">Orders Management</h3>
                </div>
            </div>

            <!-- Alert for messages -->
            <div id="orderMessage"></div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="ordersTable">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm status-select" 
                                                data-order-id="<?php echo $order['order_id']; ?>"
                                                data-original-status="<?php echo $order['status_id']; ?>">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo $status['status_id']; ?>" 
                                                        <?php echo ($status['status_id'] == $order['status_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status['status_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <div><?php echo $order['items_count']; ?> items</div>
                                        <small class="text-muted">$<?php echo number_format($order['subtotal'], 2); ?> + $<?php echo number_format($order['delivery_fee'], 2); ?> delivery</small>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>
<script src="assets/js/orders.js"></script>

</body>
</html> 