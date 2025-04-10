<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to update your cart']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action']) || !isset($data['cart_item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_item_id = filter_var($data['cart_item_id'], FILTER_VALIDATE_INT);
$action = $data['action'];

if (!$cart_item_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart item ID']);
    exit();
}

try {
    // Verify the cart item belongs to the user
    $stmt = $mysqli->prepare("
        SELECT * FROM cart_items 
        WHERE cart_item_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $cart_item_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit();
    }
    
    $cart_item = $result->fetch_assoc();
    
    switch ($action) {
        case 'update':
            if (!isset($data['quantity'])) {
                echo json_encode(['success' => false, 'message' => 'Quantity is required']);
                exit();
            }
            
            $quantity = filter_var($data['quantity'], FILTER_VALIDATE_INT);
            
            if (!$quantity || $quantity < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
                exit();
            }
            
            // Update quantity and recalculate total price
            $total_price = $cart_item['single_meal_price'] * $quantity;
            
            $stmt = $mysqli->prepare("
                UPDATE cart_items 
                SET quantity = ?, total_price = ?, updated_at = CURRENT_TIMESTAMP
                WHERE cart_item_id = ?
            ");
            $stmt->bind_param("idi", $quantity, $total_price, $cart_item_id);
            $stmt->execute();
            
            $message = 'Cart item quantity updated successfully';
            break;
            
        case 'update_notes':
            if (!isset($data['notes'])) {
                echo json_encode(['success' => false, 'message' => 'Notes are required']);
                exit();
            }
            
            $notes = filter_var($data['notes'], FILTER_SANITIZE_STRING);
            
            $stmt = $mysqli->prepare("
                UPDATE cart_items 
                SET customization_notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE cart_item_id = ?
            ");
            $stmt->bind_param("si", $notes, $cart_item_id);
            $stmt->execute();
            
            $message = 'Special instructions updated successfully';
            break;
            
        case 'remove':
            // Delete cart item and its ingredients
            $mysqli->begin_transaction();
            
            // Delete ingredients first (due to foreign key constraint)
            $stmt = $mysqli->prepare("DELETE FROM cart_item_ingredients WHERE cart_item_id = ?");
            $stmt->bind_param("i", $cart_item_id);
            $stmt->execute();
            
            // Delete cart item
            $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
            $stmt->bind_param("i", $cart_item_id);
            $stmt->execute();
            
            $mysqli->commit();
            
            $message = 'Cart item removed successfully';
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
    
    // Get updated cart total
    $stmt = $mysqli->prepare("
        SELECT SUM(total_price) as total_amount, SUM(quantity) as total_items
        FROM cart_items 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_totals = $result->fetch_assoc();
    
    $total_amount = $cart_totals['total_amount'] ?? 0;
    $total_items = $cart_totals['total_items'] ?? 0;
    
    // Update session cart count for consistency
    $_SESSION['cart_count'] = $total_items;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'total_amount' => $total_amount,
        'total_items' => $total_items
    ]);
    
} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }
    error_log("Error updating cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating your cart']);
}

// Close the database connection
$mysqli->close();
?>
