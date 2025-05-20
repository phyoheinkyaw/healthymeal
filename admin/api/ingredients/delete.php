<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['ingredient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Ingredient ID is required']);
    exit();
}

$ingredient_id = filter_var($_POST['ingredient_id'], FILTER_VALIDATE_INT);
if (!$ingredient_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ingredient ID']);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Check if ingredient exists
    $check_stmt = $mysqli->prepare("SELECT ingredient_id FROM ingredients WHERE ingredient_id = ?");
    $check_stmt->bind_param("i", $ingredient_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Ingredient not found");
    }

    // Check if ingredient is used in any meal kits
    $check_meal_kits = $mysqli->prepare("
        SELECT COUNT(*) as count 
        FROM meal_kit_ingredients 
        WHERE ingredient_id = ?
    ");
    $check_meal_kits->bind_param("i", $ingredient_id);
    $check_meal_kits->execute();
    $meal_kit_count = $check_meal_kits->get_result()->fetch_assoc()['count'];

    if ($meal_kit_count > 0) {
        throw new Exception("Cannot delete ingredient as it is used in one or more meal kits");
    }

    // Delete the ingredient
    $stmt = $mysqli->prepare("DELETE FROM ingredients WHERE ingredient_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare delete statement: " . $mysqli->error);
    }

    $stmt->bind_param("i", $ingredient_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute delete statement: " . $stmt->error);
    }

    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ingredient deleted successfully'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting ingredient: ' . $e->getMessage()
    ]);
} 