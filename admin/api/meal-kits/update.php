<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
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

// Fetch previous image_url from DB before any changes
$prev_image_url = null;
$prev_img_stmt = $mysqli->prepare("SELECT image_url FROM meal_kits WHERE meal_kit_id = ?");
$prev_img_stmt->bind_param("i", $meal_kit_id);
$prev_img_stmt->execute();
$prev_img_stmt->bind_result($prev_image_url);
$prev_img_stmt->fetch();
$prev_img_stmt->close();

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
}

// Handle image URL or keep it null
if (!$image_uploaded) {
    if (!empty($_POST['image_url'])) {
        // If it's a valid URL, use it directly
        if (filter_var($_POST['image_url'], FILTER_VALIDATE_URL)) {
            $image_url = $_POST['image_url'];
        } else {
            // If it's not a valid URL but not empty, it might be a relative path from an upload
            $image_url = $_POST['image_url'];
        }
    } else {
        // If no image uploaded and no image_url provided, fetch existing image_url from DB
        $stmt_img = $mysqli->prepare("SELECT image_url FROM meal_kits WHERE meal_kit_id = ?");
        $stmt_img->bind_param("i", $meal_kit_id);
        $stmt_img->execute();
        $stmt_img->bind_result($existing_image_url);
        if ($stmt_img->fetch()) {
            $image_url = $existing_image_url;
        }
        $stmt_img->close();
    }
}

// If a new image was uploaded or a new image_url was provided, and the old image was a file, delete the old file
if ((
        $image_uploaded || 
        (!empty($_POST['image_url']) && preg_match('/^https?:\\/\\//i', $_POST['image_url']) && !preg_match('/^https?:\\/\\//i', $prev_image_url))
    ) && !empty($meal_kit_id)) {
    if (!empty($prev_image_url) && !preg_match('/^https?:\\/\\//i', $prev_image_url)) {
        $img_path = realpath(__DIR__ . '/../../../uploads/meal-kits/' . $prev_image_url);
        if ($img_path && file_exists($img_path)) {
            unlink($img_path);
        }
    }
}

// Validate data
if (!$meal_kit_id || !$category_id || !$preparation_price || !$base_calories) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

// Parse ingredients and quantities from JSON if sent as string (AJAX)
if (!empty($_POST['ingredients']) && is_string($_POST['ingredients'])) {
    $_POST['ingredients'] = json_decode($_POST['ingredients'], true);
}
if (!empty($_POST['quantities']) && is_string($_POST['quantities'])) {
    $_POST['quantities'] = json_decode($_POST['quantities'], true);
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
    // If no rows were affected, check if the meal kit actually exists
    if ($stmt->affected_rows === 0) {
        $check_stmt = $mysqli->prepare("SELECT meal_kit_id FROM meal_kits WHERE meal_kit_id = ?");
        $check_stmt->bind_param("i", $meal_kit_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows === 0) {
            throw new Exception("Meal kit not found");
        }
        // If found but no change, just proceed (do not throw error)
        $check_stmt->close();
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