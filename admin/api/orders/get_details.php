<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
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
    // Get order details with customer information
    $stmt = $mysqli->prepare("
        SELECT 
            o.*,
            os.status_name,
            u.full_name as customer_name,
            u.email as customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        JOIN order_status os ON o.status_id = os.status_id
        WHERE o.order_id = ?
    ");

    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $order_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        throw new Exception('Order not found');
    }
    $stmt->close();

    // Get order items with meal kit details
    $stmt = $mysqli->prepare("
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
        $order_items[] = $item;
        $subtotal += $item['price_per_unit'] * $item['quantity'];
    }

    // Build HTML for order details
    $statusClass = match($order['status_name']) {
        'Pending' => 'warning',
        'Processing' => 'info',
        'Shipped' => 'primary',
        'Delivered' => 'success',
        'Cancelled' => 'danger',
        default => 'secondary'
    };

    $html = '
    <div class="order-details">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6>Order Information</h6>
                <p class="mb-1"><strong>Order ID:</strong> #' . $order['order_id'] . '</p>
                <p class="mb-1"><strong>Date:</strong> ' . date('F d, Y h:i A', strtotime($order['created_at'])) . '</p>
                <p class="mb-1">
                    <strong>Status:</strong> 
                    <span class="badge bg-' . $statusClass . '">' . htmlspecialchars($order['status_name']) . '</span>
                </p>
                <p class="mb-1"><strong>Payment Method:</strong> ' . htmlspecialchars($order['payment_method']) . '</p>
            </div>
            <div class="col-md-6">
                <h6>Customer Information</h6>
                <p class="mb-1"><strong>Name:</strong> ' . htmlspecialchars($order['customer_name']) . '</p>
                <p class="mb-1"><strong>Email:</strong> ' . htmlspecialchars($order['customer_email']) . '</p>
                <p class="mb-1"><strong>Contact:</strong> ' . htmlspecialchars($order['contact_number']) . '</p>
                <p class="mb-1"><strong>Delivery Address:</strong> ' . htmlspecialchars($order['delivery_address']) . '</p>
                <p class="mb-1"><strong>Delivery Notes:</strong> ' . htmlspecialchars($order['delivery_notes'] ?? 'None') . '</p>
            </div>
        </div>

        <h6>Order Items</h6>
        <div class="table-responsive mb-4">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Details</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($order_items as $item) {
        $html .= '
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="' . htmlspecialchars($item['image_url']) . '" 
                                     alt="' . htmlspecialchars($item['meal_kit_name']) . '"
                                     class="me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                <div>
                                    <div>' . htmlspecialchars($item['meal_kit_name']) . '</div>
                                    <small class="text-muted">' . htmlspecialchars($item['customization_notes'] ?? 'No customization') . '</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small class="d-block">Calories: ' . $item['base_calories'] . ' kcal</small>
                            <small class="d-block">Cook Time: ' . $item['cooking_time'] . ' mins</small>
                            <small class="d-block">Servings: ' . $item['servings'] . '</small>
                        </td>
                        <td class="text-center">' . $item['quantity'] . '</td>
                        <td class="text-end">$' . number_format($item['price_per_unit'], 2) . '</td>
                        <td class="text-end">$' . number_format($item['price_per_unit'] * $item['quantity'], 2) . '</td>
                    </tr>';
    }

    $html .= '
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end">$' . number_format($subtotal, 2) . '</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Delivery Fee:</strong></td>
                        <td class="text-end">$' . number_format($order['delivery_fee'], 2) . '</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong>$' . number_format($subtotal + $order['delivery_fee'], 2) . '</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>';

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 