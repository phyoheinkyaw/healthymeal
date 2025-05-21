<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
$required_fields = ['name', 'calories_per_100g', 'protein_per_100g', 'carbs_per_100g', 'fat_per_100g', 'price_per_100g'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Sanitize and validate input
$name = filter_var(trim($data['name']), FILTER_SANITIZE_STRING);
$calories = filter_var($data['calories_per_100g'], FILTER_VALIDATE_FLOAT);
$protein = filter_var($data['protein_per_100g'], FILTER_VALIDATE_FLOAT);
$carbs = filter_var($data['carbs_per_100g'], FILTER_VALIDATE_FLOAT);
$fat = filter_var($data['fat_per_100g'], FILTER_VALIDATE_FLOAT);
$price = filter_var($data['price_per_100g'], FILTER_VALIDATE_FLOAT);
$is_meat = isset($data['is_meat']) && $data['is_meat'] ? 1 : 0;
$is_vegetarian = isset($data['is_vegetarian']) && $data['is_vegetarian'] ? 1 : 0;
$is_vegan = isset($data['is_vegan']) && $data['is_vegan'] ? 1 : 0;
$is_halal = isset($data['is_halal']) && $data['is_halal'] ? 1 : 0;

if (!$calories || !$protein || !$carbs || !$fat || !$price) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid numerical values provided'
    ]);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Check for duplicate ingredient name (case-insensitive)
    $dup_stmt = $mysqli->prepare("SELECT ingredient_id FROM ingredients WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $dup_stmt->bind_param("s", $name);
    $dup_stmt->execute();
    $dup_stmt->store_result();
    if ($dup_stmt->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ingredient with this name already exists.'
        ]);
        exit();
    }

    $stmt = $mysqli->prepare("
        INSERT INTO ingredients (
            name, calories_per_100g, protein_per_100g, carbs_per_100g, 
            fat_per_100g, price_per_100g, is_meat, is_vegetarian, is_vegan, is_halal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $mysqli->error);
    }

    $stmt->bind_param(
        "sdddddiiii",
        $name, $calories, $protein, $carbs, $fat, $price,
        $is_meat, $is_vegetarian, $is_vegan, $is_halal
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $ingredient_id = $mysqli->insert_id;
    
    // Get the newly created ingredient
    $get_stmt = $mysqli->prepare("SELECT * FROM ingredients WHERE ingredient_id = ?");
    $get_stmt->bind_param("i", $ingredient_id);
    $get_stmt->execute();
    $ingredient = $get_stmt->get_result()->fetch_assoc();
    
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient created successfully',
        'ingredient' => $ingredient
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error creating ingredient: ' . $e->getMessage()
    ]);
}