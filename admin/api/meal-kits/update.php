<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate required fields
$required_fields = ['meal_kit_id', 'name', 'description', 'category_id', 'preparation_price', 'base_calories'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit();
    }
}

// Sanitize and validate input
$meal_kit_id = filter_var($_POST['meal_kit_id'], FILTER_VALIDATE_INT);
$name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
$description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
$category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
$preparation_price = filter_var($_POST['preparation_price'], FILTER_VALIDATE_FLOAT);
$base_calories = filter_var($_POST['base_calories'], FILTER_VALIDATE_INT);
$cooking_time = !empty($_POST['cooking_time']) ? filter_var($_POST['cooking_time'], FILTER_VALIDATE_INT) : null;
$servings = !empty($_POST['servings']) ? filter_var($_POST['servings'], FILTER_VALIDATE_INT) : null;

// Handle image URL or keep it null
$image_url = null;
if (!empty($_POST['image_url'])) {
    // If it's a valid URL, use it directly
    if (filter_var($_POST['image_url'], FILTER_VALIDATE_URL)) {
        $image_url = $_POST['image_url'];
    } else {
        // If it's not a valid URL but not empty, it might be a relative path from an upload
        $image_url = $_POST['image_url'];
    }
}

// Validate data
if (!$meal_kit_id || !$category_id || !$preparation_price || !$base_calories) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Update meal kit
    $stmt = $mysqli->prepare("
        UPDATE meal_kits SET
            name = ?,
            description = ?,
            category_id = ?,
            preparation_price = ?,
            base_calories = ?,
            cooking_time = ?,
            servings = ?,
            image_url = ?
        WHERE meal_kit_id = ?
    ");

    $stmt->bind_param(
        "ssidiiisi",
        $name, $description, $category_id, $preparation_price, $base_calories,
        $cooking_time, $servings, $image_url, $meal_kit_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to update meal kit");
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("Meal kit not found");
    }

    // Update meal kit ingredients if provided
    if (!empty($_POST['ingredients']) && is_array($_POST['ingredients'])) {
        // First delete existing ingredients for this meal kit
        $delete_stmt = $mysqli->prepare("DELETE FROM meal_kit_ingredients WHERE meal_kit_id = ?");
        $delete_stmt->bind_param("i", $meal_kit_id);
        $delete_stmt->execute();
        
        // Then insert the new ingredients
        $ingredient_stmt = $mysqli->prepare("INSERT INTO meal_kit_ingredients (meal_kit_id, ingredient_id, default_quantity) VALUES (?, ?, ?)");
        
        // Track processed ingredients to avoid duplicates
        $processed_ingredients = [];
        
        foreach ($_POST['ingredients'] as $ingredient_id) {
            // Make sure ingredient_id is a valid integer
            $ingredient_id = filter_var($ingredient_id, FILTER_VALIDATE_INT);
            if (!$ingredient_id || in_array($ingredient_id, $processed_ingredients)) continue;
            
            // Add to processed list to avoid duplicates
            $processed_ingredients[] = $ingredient_id;
            
            // Get quantity for this ingredient
            $quantity = isset($_POST['quantities'][$ingredient_id]) ? 
                        filter_var($_POST['quantities'][$ingredient_id], FILTER_VALIDATE_INT) : 100;
            
            // Ensure quantity is at least 1
            $quantity = max(1, $quantity);
            
            $ingredient_stmt->bind_param("iii", $meal_kit_id, $ingredient_id, $quantity);
            if (!$ingredient_stmt->execute()) {
                throw new Exception("Failed to add ingredient: " . $mysqli->error);
            }
        }
    }

    // Commit transaction
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Meal kit updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}