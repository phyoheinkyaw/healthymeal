<?php
// NOTE: This endpoint is for adding items to the cart for logged-in users only.
// Cart data is stored in the database (cart_items, cart_item_ingredients), not in the session.
// The session is used only for user_id and cart count display.

session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to cart']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['meal_kit_id']) || !isset($data['ingredients'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$user_id = $_SESSION['user_id'];
$meal_kit_id = filter_var($data['meal_kit_id'], FILTER_VALIDATE_INT);
$ingredients = $data['ingredients'];
$customization_notes = isset($data['customization_notes']) ? $data['customization_notes'] : '';
$meal_quantity = isset($data['quantity']) ? filter_var($data['quantity'], FILTER_VALIDATE_INT) : 1;
$single_meal_price = isset($data['total_price']) ? filter_var($data['total_price'], FILTER_VALIDATE_FLOAT) : 0;

// Ensure quantity is at least 1
if (!$meal_quantity || $meal_quantity < 1) {
    $meal_quantity = 1;
}

if (!$meal_kit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal kit ID']);
    exit();
}

// Calculate total price based on quantity
$total_price = $single_meal_price * $meal_quantity;

// Begin transaction
$mysqli->begin_transaction();

try {
    // Check if the same meal kit with the same customization already exists in the cart
    $stmt = $mysqli->prepare("
        SELECT cart_item_id, quantity 
        FROM cart_items 
        WHERE user_id = ? AND meal_kit_id = ? AND customization_notes = ?
    ");
    $stmt->bind_param("iis", $user_id, $meal_kit_id, $customization_notes);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing cart item
        $cart_item = $result->fetch_assoc();
        $cart_item_id = $cart_item['cart_item_id'];
        $new_quantity = $cart_item['quantity'] + $meal_quantity;
        $new_total_price = $single_meal_price * $new_quantity;
        
        $stmt = $mysqli->prepare("
            UPDATE cart_items 
            SET quantity = ?, total_price = ?, updated_at = CURRENT_TIMESTAMP
            WHERE cart_item_id = ?
        ");
        $stmt->bind_param("idi", $new_quantity, $new_total_price, $cart_item_id);
        $stmt->execute();
        
        // Delete existing ingredients to replace with new ones
        $stmt = $mysqli->prepare("DELETE FROM cart_item_ingredients WHERE cart_item_id = ?");
        $stmt->bind_param("i", $cart_item_id);
        $stmt->execute();
    } else {
        // Insert new cart item
        $stmt = $mysqli->prepare("
            INSERT INTO cart_items (user_id, meal_kit_id, quantity, customization_notes, single_meal_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiisdd", $user_id, $meal_kit_id, $meal_quantity, $customization_notes, $single_meal_price, $total_price);
        $stmt->execute();
        $cart_item_id = $mysqli->insert_id;
    }
    
    // Insert ingredient details
    foreach ($ingredients as $ingredient_id => $ingredient_quantity) {
        // Get ingredient price
        $ing_stmt = $mysqli->prepare("SELECT price_per_100g FROM ingredients WHERE ingredient_id = ?");
        $ing_stmt->bind_param("i", $ingredient_id);
        $ing_stmt->execute();
        $ingredient = $ing_stmt->get_result()->fetch_assoc();
        
        if ($ingredient) {
            $price = ($ingredient['price_per_100g'] * $ingredient_quantity / 100);
            
            $stmt = $mysqli->prepare("
                INSERT INTO cart_item_ingredients (cart_item_id, ingredient_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iidd", $cart_item_id, $ingredient_id, $ingredient_quantity, $price);
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    error_log("Error adding item to cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding item to cart']);
}

// Close the database connection
$mysqli->close();
?>
