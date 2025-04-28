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

$user_id = $_SESSION['user_id'];

// Check if the order belongs to the user
$stmt = $mysqli->prepare("
    SELECT order_id 
    FROM orders 
    WHERE order_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Get order items with their ingredients
$stmt = $mysqli->prepare("
    SELECT oi.order_item_id, oi.meal_kit_id, oi.quantity, oi.price_per_unit, oi.customization_notes
    FROM order_items oi
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    $total_items = 0;
    $inactive_meal_kits = [];
    $total_meal_kits = 0;
    
    // Add items to cart
    while ($item = $items->fetch_assoc()) {
        $total_meal_kits++;
        
        // Check if the meal kit is active
        $active_check = $mysqli->prepare("
            SELECT name, is_active 
            FROM meal_kits 
            WHERE meal_kit_id = ?
        ");
        $active_check->bind_param("i", $item['meal_kit_id']);
        $active_check->execute();
        $meal_kit_info = $active_check->get_result()->fetch_assoc();
        
        // If meal kit is inactive, skip it and add to inactive list
        if (!$meal_kit_info || $meal_kit_info['is_active'] != 1) {
            $inactive_meal_kits[] = [
                'id' => $item['meal_kit_id'],
                'name' => $meal_kit_info ? $meal_kit_info['name'] : 'Unknown Meal Kit'
            ];
            continue;
        }
        
        // Insert cart item
        $stmt = $mysqli->prepare("
            INSERT INTO cart_items (user_id, meal_kit_id, quantity, customization_notes, single_meal_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $total_price = $item['price_per_unit'] * $item['quantity'];
        $stmt->bind_param("iisidd", $user_id, $item['meal_kit_id'], $item['quantity'], $item['customization_notes'], $item['price_per_unit'], $total_price);
        $stmt->execute();
        
        $cart_item_id = $mysqli->insert_id;
        $total_items += $item['quantity'];
        
        // Get order item ingredients
        $ing_stmt = $mysqli->prepare("
            SELECT oii.ingredient_id, oii.custom_grams
            FROM order_item_ingredients oii
            WHERE oii.order_item_id = ?
        ");
        
        if ($ing_stmt) {
            $ing_stmt->bind_param("i", $item['order_item_id']);
            $ing_stmt->execute();
            $ingredients = $ing_stmt->get_result();
            
            // Insert cart item ingredients
            if ($ingredients->num_rows > 0) {
                $ing_insert_stmt = $mysqli->prepare("
                    INSERT INTO cart_item_ingredients (cart_item_id, ingredient_id, quantity, price)
                    VALUES (?, ?, ?, 0)
                ");
                
                while ($ingredient = $ingredients->fetch_assoc()) {
                    $ing_insert_stmt->bind_param("iid", $cart_item_id, $ingredient['ingredient_id'], $ingredient['custom_grams']);
                    $ing_insert_stmt->execute();
                }
            } else {
                // If no ingredients found in order_item_ingredients, get default ingredients from meal_kit_ingredients
                $def_ing_stmt = $mysqli->prepare("
                    SELECT mki.ingredient_id, mki.default_quantity, i.price_per_100g
                    FROM meal_kit_ingredients mki
                    JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
                    WHERE mki.meal_kit_id = ?
                ");
                
                $def_ing_stmt->bind_param("i", $item['meal_kit_id']);
                $def_ing_stmt->execute();
                $default_ingredients = $def_ing_stmt->get_result();
                
                if ($default_ingredients->num_rows > 0) {
                    $ing_insert_stmt = $mysqli->prepare("
                        INSERT INTO cart_item_ingredients (cart_item_id, ingredient_id, quantity, price)
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    while ($def_ingredient = $default_ingredients->fetch_assoc()) {
                        $price = ($def_ingredient['price_per_100g'] * $def_ingredient['default_quantity'] / 100);
                        $ing_insert_stmt->bind_param("iidd", $cart_item_id, $def_ingredient['ingredient_id'], $def_ingredient['default_quantity'], $price);
                        $ing_insert_stmt->execute();
                    }
                }
            }
        }
    }
    
    // Update session cart count
    $_SESSION['cart_count'] = $total_items;
    
    // Commit transaction
    $mysqli->commit();
    
    // Prepare response message
    if ($total_items > 0) {
        $message = "Added $total_items items to your cart.";
        if (!empty($inactive_meal_kits)) {
            $inactive_names = array_map(function($mk) { return $mk['name']; }, $inactive_meal_kits);
            $message .= " The following meal kits are no longer available and were not added: " . implode(", ", $inactive_names) . ".";
        }
        $success = true;
    } else {
        // All meal kits were inactive
        if (count($inactive_meal_kits) == $total_meal_kits) {
            $inactive_names = array_map(function($mk) { return $mk['name']; }, $inactive_meal_kits);
            $message = "No items were added to your cart. All meal kits from this order are no longer available: " . implode(", ", $inactive_names) . ".";
            $success = true; // Still return success so we don't show a generic error
        } else {
            $message = "No items were added to your cart.";
            $success = false;
        }
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'total_items' => $total_items,
        'inactive_meal_kits' => $inactive_meal_kits,
        'cartCount' => $total_items // Keep this for backward compatibility
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }
    
    error_log("Error adding items to cart: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adding items to cart',
        'debug' => $e->getMessage()
    ]);
}

// Close the database connection
$mysqli->close();
?>