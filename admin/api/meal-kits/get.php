<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Meal kit ID is required']);
    exit();
}

$meal_kit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$meal_kit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal kit ID']);
    exit();
}

// Get meal kit details
$stmt = $mysqli->prepare("
    SELECT mk.*, c.name as category_name
    FROM meal_kits mk
    LEFT JOIN categories c ON mk.category_id = c.category_id
    WHERE mk.meal_kit_id = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$stmt->bind_param("i", $meal_kit_id);
$stmt->execute();
$meal_kit = $stmt->get_result()->fetch_assoc();

if (!$meal_kit) {
    echo json_encode(['success' => false, 'message' => 'Meal kit not found']);
    exit();
}

// Get ingredients
$stmt = $mysqli->prepare("
    SELECT i.*, mki.default_quantity
    FROM meal_kit_ingredients mki
    JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    WHERE mki.meal_kit_id = ?
");

$stmt->bind_param("i", $meal_kit_id);
$stmt->execute();
$ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'meal_kit' => $meal_kit,
    'ingredients' => $ingredients
]); 