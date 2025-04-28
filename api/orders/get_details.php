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

// --- Enhanced, more visually appealing UI for order details modal ---
$html = '
<div class="order-details p-4 rounded shadow-lg bg-white">
    <div class="row mb-4 g-3 align-items-stretch">
        <div class="col-md-6">
            <div class="p-3 h-100 border rounded bg-light-subtle">
                <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-receipt-cutoff me-1"></i>Order Information</h6>
                <p class="mb-1"><span class="fw-semibold">Order Date:</span> <span class="text-body">' . date('F d, Y', strtotime($order['created_at'])) . '</span></p>
                <p class="mb-1"><span class="fw-semibold">Status:</span> <span class="badge bg-' .
                    match($order['status_name']) {
                        'Pending' => 'warning',
                        'Processing' => 'info',
                        'Shipped' => 'primary',
                        'Delivered' => 'success',
                        'Cancelled' => 'danger',
                        default => 'secondary'
                    } . '">' . htmlspecialchars($order['status_name']) . '</span></p>
                <p class="mb-1"><span class="fw-semibold">Payment:</span> ' . htmlspecialchars($order['payment_method']) . '</p>
                ' .
                ($order['payment_method'] !== 'Cash on Delivery' ?
                    (!empty($order['transfer_slip'])
                        ? '<div class="mb-2"><span class="fw-semibold">Transfer Slip:</span><br><img src="' . htmlspecialchars($order['transfer_slip']) . '" alt="Transfer Slip" class="img-fluid rounded shadow border mt-2" style="max-width:320px;max-height:180px;">'
                        . '<br><a href="' . htmlspecialchars($order['transfer_slip']) . '" class="btn btn-outline-primary btn-sm mt-2" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>View Slip in New Tab</a></div>'
                        : '<div class="mb-2"><span class="fw-semibold">Transfer Slip:</span> N/A</div>')
                :
                    ''
                ) .
                '
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 h-100 border rounded bg-light-subtle">
                <h6 class="fw-bold mb-2 text-success"><i class="bi bi-truck me-1"></i>Delivery Information</h6>
                <p class="mb-1"><span class="fw-semibold">Address:</span> ' . htmlspecialchars($order['delivery_address']) . '</p>
                <p class="mb-1"><span class="fw-semibold">Contact:</span> ' . htmlspecialchars($order['contact_number']) . '</p>
                <p class="mb-1"><span class="fw-semibold">Notes:</span> ' . htmlspecialchars($order['delivery_notes'] ?? 'None') . '</p>
            </div>
        </div>
    </div>

    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-basket2 me-1"></i>Order Items</h6>
    <div class="table-responsive">
        <table class="table align-middle table-hover border rounded shadow-sm bg-white">
            <thead class="table-primary">
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';

$total = 0;
foreach ($order_items as $item) {
    $itemTotal = $item['quantity'] * $item['price_per_unit'];
    $total += $itemTotal;
    $image_url = get_meal_kit_image_url($item['image_url'], $item['meal_kit_name']);
    $html .= '
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <img src="' . $image_url . '" 
                         alt="' . htmlspecialchars($item['meal_kit_name']) . '"
                         class="me-2 rounded meal-kit-thumb"
                         style="width: 70px; height: 70px; object-fit: cover; border: 2px solid #eee; background: #fff;">
                    <div>
                        <h6 class="mb-0">' . htmlspecialchars($item['meal_kit_name']) . '</h6>';
                        if (!empty($item['customization_notes'])) {
                            $html .= '<small class="text-muted"><strong>Special Instructions:</strong> ' . 
                                htmlspecialchars($item['customization_notes']) . '</small><br>';
                        }
                        if (!empty($item['customizations'])) {
                            $html .= '<small class="text-muted"><strong>Ingredient Customizations:</strong><ul class="mb-0">';
                            foreach ($item['customizations'] as $custom) {
                                $html .= '<li>' . htmlspecialchars($custom['ingredient_name']) . ': ' . htmlspecialchars($custom['custom_grams']) . 'g</li>';
                            }
                            $html .= '</ul></small>';
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
        <div class="btn-group">';
$html .= '<button type="button" class="btn btn-primary" onclick="reorderItems(' . $order_id . ')"><i class="bi bi-cart-plus"></i> Reorder</button>';
if ($order['status_id'] == 1) {
    $html .= '<button type="button" class="btn btn-danger ms-2" onclick="cancelOrder(' . $order_id . ')"><i class="bi bi-x-circle"></i> Cancel Order</button>';
}
$html .= '</div></div></div>';

echo json_encode([
    'success' => true,
    'html' => $html
]); 