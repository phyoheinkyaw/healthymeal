<?php
session_start();
require_once 'config/connection.php';

// Redirect to orders page with success parameter
if (isset($_GET['order_id']) && filter_var($_GET['order_id'], FILTER_VALIDATE_INT)) {
    header("Location: orders.php?checkout_success=true&order_id=" . $_GET['order_id']);
    exit();
}

// Original code below for backwards compatibility
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if order_id is provided
if (!isset($_GET['order_id']) || !filter_var($_GET['order_id'], FILTER_VALIDATE_INT)) {
    header("Location: orders.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Fetch order details to verify order belongs to user and display information
$stmt = $mysqli->prepare("
    SELECT o.*, os.status_name, sm.name as shipping_method, sm.estimated_days, ps.payment_method
    FROM orders o
    LEFT JOIN order_status os ON o.status_id = os.status_id
    LEFT JOIN shipping_methods sm ON o.shipping_method_id = sm.shipping_method_id
    LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: orders.php");
    exit();
}

$order = $result->fetch_assoc();

// Fetch order items
$items_stmt = $mysqli->prepare("
    SELECT oi.*, mk.name as meal_kit_name, mk.image_url
    FROM order_items oi
    LEFT JOIN meal_kits mk ON oi.meal_kit_id = mk.meal_kit_id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$order_items = [];
while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}

// Helper function for image URLs
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0];
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-success mb-4">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i>Order Confirmed!</h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">Thank you for your order!</h4>
                            <p class="lead">Your order has been received and is being processed.</p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>Order Information</h5>
                                        <p class="mb-1"><strong>Order Number:</strong> #<?php echo $order_id; ?></p>
                                        <p class="mb-1"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($order['created_at'])); ?></p>
                                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?php echo $order['status_name'] === 'pending' ? 'warning' : 'primary'; ?>"><?php echo ucfirst($order['status_name']); ?></span></p>
                                        <p class="mb-1"><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                                        <p class="mb-0"><strong>Total:</strong> <span class="fw-bold text-primary"><?php echo number_format($order['total_amount'], 0); ?> MMK</span></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-truck me-2"></i>Delivery Information</h5>
                                        <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($order['delivery_address']); ?></p>
                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_number']); ?></p>
                                        <p class="mb-1"><strong>Shipping Method:</strong> <?php echo htmlspecialchars($order['shipping_method']); ?></p>
                                        <p class="mb-1"><strong>Delivery Date:</strong> <?php echo date('F d, Y', strtotime($order['expected_delivery_date'])); ?></p>
                                        <?php if (!empty($order['preferred_delivery_time'])): ?>
                                        <p class="mb-0"><strong>Delivery Time:</strong> <?php echo date('g:i A', strtotime($order['preferred_delivery_time'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mb-3"><i class="bi bi-basket2 me-2"></i>Order Items</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th width="100">Qty</th>
                                        <th width="120">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo get_meal_kit_image_url($item['image_url'], $item['meal_kit_name']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['meal_kit_name']); ?>" 
                                                     class="me-2" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['meal_kit_name']); ?></h6>
                                                    <?php if (!empty($item['customization_notes'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($item['customization_notes']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['price_per_unit'] * $item['quantity'], 0); ?> MMK</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold">Subtotal:</td>
                                        <td class="text-end"><?php echo number_format($order['subtotal'], 0); ?> MMK</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold">Tax:</td>
                                        <td class="text-end"><?php echo number_format($order['tax'], 0); ?> MMK</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold">Delivery Fee:</td>
                                        <td class="text-end"><?php echo number_format($order['delivery_fee'], 0); ?> MMK</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold">Total:</td>
                                        <td class="text-end fw-bold text-primary"><?php echo number_format($order['total_amount'], 0); ?> MMK</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if ($order['payment_method'] !== 'Cash on Delivery' && empty($order['transfer_slip'])): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> Please upload your payment slip to complete your order.
                            <div class="mt-2">
                                <a href="orders.php" class="btn btn-warning btn-sm">View Orders</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex flex-column flex-sm-row justify-content-center mt-4 gap-2">
                            <a href="orders.php" class="btn btn-outline-primary">
                                <i class="bi bi-list-ul me-1"></i> View All Orders
                            </a>
                            <a href="meal-kits.php" class="btn btn-primary">
                                <i class="bi bi-bag-plus me-1"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

</body>

</html> 