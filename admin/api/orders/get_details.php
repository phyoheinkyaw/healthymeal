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

// Helper function to get the correct meal kit image URL (copied from meal-kits.php)
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
            oi.*, mk.name as meal_kit_name, mk.image_url, mk.base_calories, mk.cooking_time, mk.servings
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
        // Fetch ingredient customizations for this order_item
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

    // Normalize status name to match Bootstrap classes (lowercase, no spaces)
    $statusRaw = strtolower(trim($order['status_name'] ?? $order['status'] ?? ''));
    $statusMap = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
    ];
    $statusClass = $statusMap[$statusRaw] ?? 'secondary';

    // Build HTML for order details
    $html = '
    <div class="order-details">
        <div class="row mb-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="p-3 rounded-4 shadow-sm h-100" style="background:linear-gradient(90deg,#e3f2fd 60%,#bbdefb 100%); border-left:6px solid #1976d2;">
                    <h6 class="fw-bold mb-2" style="color:#1976d2;"><i class="bi bi-receipt"></i> Order Information</h6>
                    <p class="mb-1"><strong>Order ID:</strong> <span style="color:#1976d2;">#' . $order['order_id'] . '</span></p>
                    <p class="mb-1"><strong>Date:</strong> <span style="color:#0288d1;">' . date('F d, Y h:i A', strtotime($order['created_at'])) . '</span></p>
                    <p class="mb-1">
                        <strong>Status:</strong> 
                        <span class="badge bg-' . $statusClass . ' fw-bold px-3 py-2 rounded-pill" style="font-size:1em;min-width:110px;letter-spacing:0.5px;">' . htmlspecialchars($order['status_name'] ?? $order['status'] ?? '') . '</span>
                    </p>
                    <p class="mb-1"><strong>Payment Method:</strong> <span style="color:#388e3c;">' . htmlspecialchars($order['payment_method']) . '</span></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 rounded-4 shadow-sm h-100" style="background:linear-gradient(90deg,#fffde7 60%,#ffe082 100%); border-left:6px solid #fbc02d;">
                    <h6 class="fw-bold mb-2" style="color:#fbc02d;"><i class="bi bi-person-circle"></i> Customer Information</h6>
                    <p class="mb-1"><strong>Name:</strong> <span style="color:#7b1fa2;">' . htmlspecialchars($order['customer_name']) . '</span></p>
                    <p class="mb-1"><strong>Email:</strong> <span style="color:#0288d1;">' . htmlspecialchars($order['customer_email']) . '</span></p>
                    <p class="mb-1"><strong>Contact:</strong> <span style="color:#388e3c;">' . htmlspecialchars($order['contact_number']) . '</span></p>
                    <p class="mb-1"><strong>Delivery Address:</strong> <span style="color:#6a1b9a;">' . htmlspecialchars($order['delivery_address']) . '</span></p>
                    <p class="mb-1"><strong>Delivery Notes:</strong> <span style="color:#ef6c00;">' . htmlspecialchars($order['delivery_notes'] ?? 'None') . '</span></p>
                </div>
            </div>
        </div>

        <div class="order-details-list">';
    foreach ($order_items as $item) {
        $img_url = get_meal_kit_image_url($item['image_url']);
        $html .= '
        <div class="order-item-box mb-3 p-3 rounded-4 shadow-sm" style="background:linear-gradient(90deg,#f3e5f5 60%,#e1bee7 100%); border:2.5px solid #7b1fa2;">
            <div class="row g-2 align-items-center flex-wrap flex-md-nowrap">
                <div class="col-4 col-sm-3 col-md-2 text-center">
                    <img src="' . htmlspecialchars($img_url) . '" alt="' . htmlspecialchars($item['meal_kit_name']) . '" class="img-thumbnail shadow-sm" style="width:100%;max-width:90px;max-height:70px;object-fit:cover;">
                </div>
                <div class="col-8 col-sm-9 col-md-10">
                    <div class="fw-bold mb-1" style="font-size:1.15em;color:#6a1b9a;text-shadow:0 1px 6px #ce93d8;">' . htmlspecialchars($item['meal_kit_name']) . '</div>';
        if (!empty($item['customization_notes'])) {
            $html .= '<div class="customization-note-box mb-2 p-2 rounded-3 d-inline-block w-100" style="background:linear-gradient(90deg,#fffde7 60%,#ffe082 100%); border-left:6px solid #fbc02d; box-shadow:0 2px 8px #ffe082; font-size:1em;">
                <span class="fw-bold text-warning" style="font-size:1.05em;"><i class="bi bi-pencil-square"></i> Note:</span> <span class="text-dark">' . htmlspecialchars($item['customization_notes']) . '</span>
            </div>';
        }
        if (!empty($item['custom_ingredients'])) {
            $html .= '<div class="custom-ingredients-box mt-1 mb-1 p-2 rounded-3" style="background: linear-gradient(90deg,#e3f2fd 60%,#bbdefb 100%); border: 2.5px solid #1976d2; box-shadow: 0 4px 16px 0 rgba(33,150,243,0.13);">
                <div class="mb-1 fw-bold" style="color:#1976d2; font-size:1.05em; letter-spacing:0.5px;"><i class="bi bi-sliders"></i> <span style="text-shadow:0 1px 8px #90caf9;">Customized Ingredients</span></div>';
            $html .= '<ul class="mb-0 ps-3" style="list-style:square inside;">';
            foreach ($item['custom_ingredients'] as $ci) {
                $html .= '<li style="margin-bottom:2px;"><span style="color:#0d47a1;font-weight:600;font-size:1em;">' . htmlspecialchars($ci['ingredient_name']) . '</span>: <span style="color:#388e3c;font-weight:700;font-size:1em;">' . htmlspecialchars($ci['custom_grams']) . 'g</span></li>';
            }
            $html .= '</ul></div>';
        }
        // Standout details
        $html .= '<div class="row mt-2 g-2">
            <div class="col-6 col-md-3">
                <div style="background:#fff3e0;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #ffcc80;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#ef6c00;font-size:1.05em;"><i class="bi bi-fire"></i> ' . $item['base_calories'] . ' kcal</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div style="background:#e8f5e9;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #a5d6a7;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#388e3c;font-size:1.05em;"><i class="bi bi-clock"></i> ' . $item['cooking_time'] . ' min</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div style="background:#e3f2fd;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #90caf9;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#1976d2;font-size:1.05em;"><i class="bi bi-people"></i> ' . $item['servings'] . '</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div style="background:#f3e5f5;border-radius:1em;padding:0.4em 0.8em 0.4em 0.8em;box-shadow:0 2px 8px #ce93d8;display:inline-block;min-width:90px;">
                    <span class="fw-bold" style="color:#7b1fa2;font-size:1.05em;"><i class="bi bi-basket"></i> Qty: ' . $item['quantity'] . '</span>
                </div>
            </div>
        </div>';
        $html .= '<div class="row mt-2 g-2">
            <div class="col-6">
                <div style="background:#fffde7;border-radius:1em;padding:0.4em 0.8em;box-shadow:0 2px 8px #fff9c4;display:inline-block;min-width:90px;">
                    <span class="text-muted">Unit:</span> <span class="fw-bold" style="color:#fbc02d;">$' . number_format($item['price_per_unit'], 2) . '</span>
                </div>
            </div>
            <div class="col-6 text-end">
                <div style="background:#e1f5fe;border-radius:1em;padding:0.4em 0.8em;box-shadow:0 2px 8px #b3e5fc;display:inline-block;min-width:90px;">
                    <span class="text-muted">Total:</span> <span class="fw-bold" style="color:#0288d1;">$' . number_format($item['price_per_unit'] * $item['quantity'], 2) . '</span>
                </div>
            </div>
        </div>';
        $html .= '</div></div></div>';
    }
    $html .= '</div>';
    // Order summary
    $html .= '<div class="order-summary-box mt-4 p-3 rounded-4 shadow-sm" style="background:linear-gradient(90deg,#fffde7 60%,#ffe082 100%); border:2.5px solid #fbc02d;">
        <div class="row g-2">
            <div class="col-12 col-sm-6 col-md-4">
                <span class="fw-bold text-muted">Subtotal:</span> <span class="fw-bold" style="color:#ef6c00;">$' . number_format($subtotal, 2) . '</span>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <span class="fw-bold text-muted">Delivery Fee:</span> <span class="fw-bold" style="color:#0288d1;">$' . number_format($order['delivery_fee'], 2) . '</span>
            </div>
            <div class="col-12 col-md-4">
                <span class="fw-bold text-muted">Total:</span> <span class="fw-bold" style="color:#7b1fa2;font-size:1.1em;">$' . number_format($subtotal + $order['delivery_fee'], 2) . '</span>
            </div>
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