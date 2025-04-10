<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get ingredient ID from query parameter
$ingredient_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$ingredient_id) {
    echo json_encode(['success' => false, 'message' => 'Ingredient ID is required']);
    exit();
}

// Fetch ingredient details
$result = $mysqli->query("
    SELECT ingredient_id, name, calories_per_100g, protein_per_100g, carbs_per_100g, 
           fat_per_100g, price_per_100g 
    FROM ingredients 
    WHERE ingredient_id = $ingredient_id
");

if ($result && $result->num_rows > 0) {
    $ingredient = $result->fetch_assoc();
    echo json_encode(['success' => true, 'ingredient' => $ingredient]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ingredient not found']);
}