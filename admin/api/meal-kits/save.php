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
$required_fields = ['name', 'description', 'category_id', 'preparation_price', 'base_calories'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit();
    }
}

// Sanitize and validate input
$name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
$description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
$category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
$preparation_price = filter_var($_POST['preparation_price'], FILTER_VALIDATE_FLOAT);
$base_calories = filter_var($_POST['base_calories'], FILTER_VALIDATE_INT);
$cooking_time = !empty($_POST['cooking_time']) ? filter_var($_POST['cooking_time'], FILTER_VALIDATE_INT) : null;
$servings = !empty($_POST['servings']) ? filter_var($_POST['servings'], FILTER_VALIDATE_INT) : null;

// Handle dietary flags
$is_meat = isset($_POST['is_meat']) && $_POST['is_meat'] === 'on';
$is_vegetarian = isset($_POST['is_vegetarian']) && $_POST['is_vegetarian'] === 'on';
$is_vegan = isset($_POST['is_vegan']) && $_POST['is_vegan'] === 'on';
$is_halal = isset($_POST['is_halal']) && $_POST['is_halal'] === 'on';

// Validate dietary flags
if ($is_meat && ($is_vegetarian || $is_vegan)) {
    echo json_encode(['success' => false, 'message' => 'A meal kit cannot be both meat-based and vegetarian/vegan']);
    exit();
}

// Handle image upload (if file provided)
$image_uploaded = false;
if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../../uploads/meal-kits/';
    $ext = pathinfo($_FILES['imageFile']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('mk_', true) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['imageFile']['tmp_name'], $targetPath)) {
        $image_url = $fileName;
        $image_uploaded = true;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
        exit();
    }
} else {
    // If no image uploaded, use image_url from POST if provided
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
}

// Validate data
if (!$category_id || !$preparation_price || !$base_calories) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Insert meal kit
    $stmt = $mysqli->prepare("
        INSERT INTO meal_kits (
            name, description, category_id, preparation_price, base_calories, 
            cooking_time, servings, image_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("ssidiiis", 
        $name, $description, $category_id, $preparation_price, $base_calories,
        $cooking_time, $servings, $image_url
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert meal kit: " . $mysqli->error);
    }
    
    $meal_kit_id = $mysqli->insert_id;
    
    // Insert ingredients if provided
    if (!empty($_POST['ingredients'])) {
        $ingredients = json_decode($_POST['ingredients'], true);
        if (is_array($ingredients)) {
            $mki_stmt = $mysqli->prepare("INSERT INTO meal_kit_ingredients (meal_kit_id, ingredient_id, default_quantity) VALUES (?, ?, ?)");
            foreach ($ingredients as $ingredient) {
                $mki_stmt->bind_param("iid", $meal_kit_id, $ingredient['id'], $ingredient['quantity']);
                if (!$mki_stmt->execute()) {
                    throw new Exception("Failed to insert ingredient: " . $mysqli->error);
                }
            }
        }
    }
    
    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'Meal kit saved successfully', 'meal_kit_id' => $meal_kit_id]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}