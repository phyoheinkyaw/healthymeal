<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to view your cart']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get cart items with meal kit details
    $stmt = $mysqli->prepare("
        SELECT ci.*, mk.name as meal_kit_name, mk.image_url, c.name as category_name
        FROM cart_items ci
        JOIN meal_kits mk ON ci.meal_kit_id = mk.meal_kit_id
        LEFT JOIN categories c ON mk.category_id = c.category_id
        WHERE ci.user_id = ?
        ORDER BY ci.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_items_result = $stmt->get_result();
    
    $cart_items = [];
    $total_amount = 0;
    
    while ($item = $cart_items_result->fetch_assoc()) {
        // Get ingredient details for each cart item
        $ing_stmt = $mysqli->prepare("
            SELECT cii.*, i.name as ingredient_name, i.calories_per_100g, i.protein_per_100g, i.carbs_per_100g, i.fat_per_100g
            FROM cart_item_ingredients cii
            JOIN ingredients i ON cii.ingredient_id = i.ingredient_id
            WHERE cii.cart_item_id = ?
        ");
        $ing_stmt->bind_param("i", $item['cart_item_id']);
        $ing_stmt->execute();
        $ingredients_result = $ing_stmt->get_result();
        
        $ingredients = [];
        $total_calories = 0;
        
        while ($ingredient = $ingredients_result->fetch_assoc()) {
            $calories = ($ingredient['calories_per_100g'] * $ingredient['quantity'] / 100);
            $total_calories += $calories;
            
            $ingredients[] = [
                'id' => $ingredient['ingredient_id'],
                'name' => $ingredient['ingredient_name'],
                'quantity' => $ingredient['quantity'],
                'price' => $ingredient['price'],
                'calories' => $calories,
                'protein' => ($ingredient['protein_per_100g'] * $ingredient['quantity'] / 100),
                'carbs' => ($ingredient['carbs_per_100g'] * $ingredient['quantity'] / 100),
                'fat' => ($ingredient['fat_per_100g'] * $ingredient['quantity'] / 100)
            ];
        }
        
        $cart_items[] = [
            'cart_item_id' => $item['cart_item_id'],
            'meal_kit_id' => $item['meal_kit_id'],
            'meal_kit_name' => $item['meal_kit_name'],
            'image_url' => $item['image_url'],
            'category_name' => $item['category_name'],
            'quantity' => $item['quantity'],
            'customization_notes' => $item['customization_notes'],
            'single_meal_price' => $item['single_meal_price'],
            'total_price' => $item['total_price'],
            'total_calories' => $total_calories,
            'ingredients' => $ingredients,
            'created_at' => $item['created_at']
        ];
        
        $total_amount += $item['total_price'];
    }
    
    // Update session cart count for consistency
    $total_items = array_sum(array_column($cart_items, 'quantity'));
    $_SESSION['cart_count'] = $total_items;
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items,
        'total_amount' => $total_amount,
        'total_items' => $total_items
    ]);
    
} catch (Exception $e) {
    error_log("Error retrieving cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while retrieving your cart']);
}

// Close the database connection
$mysqli->close();
?>
