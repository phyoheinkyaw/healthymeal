<?php
session_start();
require_once '../../config/connection.php';

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
    SELECT o.*, os.status_name
    FROM orders o
    LEFT JOIN order_status os ON o.status_id = os.status_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
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

// Build HTML for order details
$html = '
<div class="order-details">
    <div class="row mb-4">
        <div class="col-md-6">
            <h6>Order Information</h6>
            <p class="mb-1">Order Date: ' . date('F d, Y', strtotime($order['created_at'])) . '</p>
            <p class="mb-1">Status: <span class="badge bg-' . 
                match($order['status_name']) {
                    'Pending' => 'warning',
                    'Processing' => 'info',
                    'Shipped' => 'primary',
                    'Delivered' => 'success',
                    'Cancelled' => 'danger',
                    default => 'secondary'
                } . '">' . 
                htmlspecialchars($order['status_name']) . '</span></p>
            <p class="mb-1">Payment Method: ' . htmlspecialchars($order['payment_method']) . '</p>
        </div>
        <div class="col-md-6">
            <h6>Delivery Information</h6>
            <p class="mb-1">Address: ' . htmlspecialchars($order['delivery_address']) . '</p>
            <p class="mb-1">Contact: ' . htmlspecialchars($order['contact_number']) . '</p>
            <p class="mb-1">Notes: ' . htmlspecialchars($order['delivery_notes'] ?? 'None') . '</p>
        </div>
    </div>

    <h6>Order Items</h6>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';

$total = 0;
while ($item = $items->fetch_assoc()) {
    $itemTotal = $item['quantity'] * $item['price_per_unit'];
    $total += $itemTotal;
    
    $html .= '
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <img src="' . ($item['image_url'] ?? 'assets/images/placeholder.jpg') . '" 
                         alt="' . htmlspecialchars($item['meal_kit_name']) . '"
                         class="me-2"
                         style="width: 50px; height: 50px; object-fit: cover;">
                    <div>
                        <h6 class="mb-0">' . htmlspecialchars($item['meal_kit_name']) . '</h6>';
                        
                        if (!empty($item['customization_notes'])) {
                            $html .= '<small class="text-muted"><strong>Special Instructions:</strong> ' . 
                            htmlspecialchars($item['customization_notes']) . '</small>';
                        }
                        
                    $html .= '</div>
                </div>
            </td>
            <td>' . $item['quantity'] . '</td>
            <td>$' . number_format($item['price_per_unit'], 2) . '</td>
            <td>$' . number_format($itemTotal, 2) . '</td>
        </tr>';
}

$html .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                    <td>$' . number_format($total, 2) . '</td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end"><strong>Delivery Fee:</strong></td>
                    <td>$' . number_format($order['delivery_fee'], 2) . '</td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                    <td><strong>$' . number_format($total + $order['delivery_fee'], 2) . '</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="d-flex justify-content-end mt-3">
        <div class="btn-group">
            <button type="button" class="btn btn-primary" onclick="reorderItems(' . $order_id . ')">
                <i class="bi bi-cart-plus"></i> Reorder
            </button>';
            
            // Only show cancel button for pending orders
            if ($order['status_id'] == 1) {
                $html .= '
                <button type="button" class="btn btn-danger ms-2" onclick="cancelOrder(' . $order_id . ')">
                    <i class="bi bi-x-circle"></i> Cancel Order
                </button>';
            }
            
        $html .= '
        </div>
    </div>
</div>';

echo json_encode([
    'success' => true,
    'html' => $html
]); 