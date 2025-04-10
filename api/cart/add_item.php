<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to your cart']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Get and validate POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['meal_kit_id']) || !isset($data['ingredients'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$meal_kit_id = filter_var($data['meal_kit_id'], FILTER_VALIDATE_INT);
$quantity = isset($data['quantity']) ? filter_var($data['quantity'], FILTER_VALIDATE_INT) : 1;
$customization_notes = isset($data['customization_notes']) ? filter_var($data['customization_notes'], FILTER_SANITIZE_STRING) : '';

if (!$meal_kit_id || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal kit ID or quantity']);
    exit();
}

// Validate ingredients
$ingredients = [];
foreach ($data['ingredients'] as $id => $qty) {
    $ingredient_id = filter_var($id, FILTER_VALIDATE_INT);
    $ingredient_qty = filter_var($qty, FILTER_VALIDATE_FLOAT);
    
    if ($ingredient_id && $ingredient_qty > 0) {
        $ingredients[$ingredient_id] = $ingredient_qty;
    }
}

if (empty($ingredients)) {
    echo json_encode(['success' => false, 'message' => 'No valid ingredients provided']);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Always insert as a new cart item, don't update existing ones
    // Insert new cart item
    $single_meal_price = isset($data['total_price']) ? filter_var($data['total_price'], FILTER_VALIDATE_FLOAT) : 0;
    $total_price = $single_meal_price * $quantity;
    
    $stmt = $mysqli->prepare("
        INSERT INTO cart_items (user_id, meal_kit_id, quantity, customization_notes, single_meal_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisidd", $user_id, $meal_kit_id, $quantity, $customization_notes, $single_meal_price, $total_price);
    $stmt->execute();
    
    $cart_item_id = $mysqli->insert_id;
    
    // Insert ingredients
    $stmt = $mysqli->prepare("
        INSERT INTO cart_item_ingredients (cart_item_id, ingredient_id, quantity, price)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($ingredients as $ingredient_id => $ingredient_qty) {
        // Get ingredient price (you may need to adjust this based on your pricing logic)
        $price_stmt = $mysqli->prepare("SELECT price_per_100g FROM ingredients WHERE ingredient_id = ?");
        $price_stmt->bind_param("i", $ingredient_id);
        $price_stmt->execute();
        $price_result = $price_stmt->get_result();
        $price_data = $price_result->fetch_assoc();
        $price = ($price_data['price_per_100g'] * $ingredient_qty / 100);
        
        $stmt->bind_param("iidd", $cart_item_id, $ingredient_id, $ingredient_qty, $price);
        $stmt->execute();
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Get updated cart count
    $stmt = $mysqli->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_count = $result->fetch_assoc();
    $total_items = $cart_count['total_items'] ?? 0;
    
    // Update session cart count for consistency
    $_SESSION['cart_count'] = $total_items;
    
    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully',
        'total_items' => $total_items
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }
    
    error_log("Error adding item to cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while adding the item to your cart']);
}

// Close the database connection
$mysqli->close();
?>