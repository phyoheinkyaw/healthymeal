<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Helper function to get image URL from DB value
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    // Get the base URL up to the project root (e.g. /hm or /yourproject)
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0]; // e.g. '/hm'
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

// Check if request is POST and JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // If JSON is invalid, return error
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    // Extract filter values
    $category_id = isset($data['category']) ? (int)$data['category'] : null;
    $dietary = isset($data['dietary']) ? $data['dietary'] : '';
    $calories = isset($data['calories']) ? $data['calories'] : '';
    $price = isset($data['price']) ? $data['price'] : '';
    $sort = isset($data['sort']) ? $data['sort'] : 'name';
    
    // Start building the query
    $query = "
        SELECT mk.*, c.name as category_name,
               SUM(i.calories_per_100g * mki.default_quantity / 100) as base_calories,
               SUM(i.price_per_100g * mki.default_quantity / 100) as ingredients_price
        FROM meal_kits mk
        LEFT JOIN categories c ON mk.category_id = c.category_id
        LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
        LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
        WHERE mk.is_active = 1
    ";
    
    // Add category filter
    if ($category_id) {
        $query .= " AND mk.category_id = " . $category_id;
    }
    
    // Group by clause
    $query .= " GROUP BY mk.meal_kit_id";
    
    // Add dietary filter - now handled via HAVING clause after aggregation
    if ($dietary) {
        switch ($dietary) {
            case 'vegetarian':
                // Check if ALL ingredients are vegetarian
                $query .= " HAVING NOT EXISTS (
                    SELECT 1 FROM meal_kit_ingredients mki2
                    JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
                    WHERE mki2.meal_kit_id = mk.meal_kit_id AND i2.is_vegetarian = 0
                )";
                break;
                
            case 'vegan':
                // Check if ALL ingredients are vegan
                $query .= " HAVING NOT EXISTS (
                    SELECT 1 FROM meal_kit_ingredients mki2
                    JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
                    WHERE mki2.meal_kit_id = mk.meal_kit_id AND i2.is_vegan = 0
                )";
                break;
                
            case 'halal':
                // Check if ALL ingredients are halal
                $query .= " HAVING NOT EXISTS (
                    SELECT 1 FROM meal_kit_ingredients mki2
                    JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
                    WHERE mki2.meal_kit_id = mk.meal_kit_id AND i2.is_halal = 0
                )";
                break;
                
            case 'meat':
                // Check if ANY ingredient is meat
                $query .= " HAVING EXISTS (
                    SELECT 1 FROM meal_kit_ingredients mki2
                    JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
                    WHERE mki2.meal_kit_id = mk.meal_kit_id AND i2.is_meat = 1
                )";
                break;
        }
    }
    
    // Add calorie filter - match dynamic ranges
    if ($calories) {
        if (preg_match('/^under(\d+)$/', $calories, $matches)) {
            $maxCalories = (int)$matches[1];
            $query .= " HAVING base_calories < $maxCalories";
        }
        else if (preg_match('/^(\d+)-(\d+)$/', $calories, $matches)) {
            $minCalories = (int)$matches[1];
            $maxCalories = (int)$matches[2];
            $query .= " HAVING base_calories BETWEEN $minCalories AND $maxCalories";
        }
        else if (preg_match('/^over(\d+)$/', $calories, $matches)) {
            $minCalories = (int)$matches[1];
            $query .= " HAVING base_calories > $minCalories";
        }
    }
    
    // Add price filter - match dynamic ranges
    if ($price) {
        if ($price == 'under10' || preg_match('/^under(\d+)$/', $price, $matches)) {
            // Support both legacy and new dynamic value formats
            $maxPrice = isset($matches[1]) ? (int)$matches[1] : 20000;
            $query .= ($calories ? " AND" : " HAVING") . " (mk.preparation_price + ingredients_price) < $maxPrice";
        }
        else if ($price == '10-20' || preg_match('/^(\d+)-(\d+)$/', $price, $matches)) {
            // Support both legacy and new dynamic value formats
            $minPrice = isset($matches[1]) ? (int)$matches[1] : 20000;
            $maxPrice = isset($matches[2]) ? (int)$matches[2] : 40000;
            $query .= ($calories ? " AND" : " HAVING") . " (mk.preparation_price + ingredients_price) BETWEEN $minPrice AND $maxPrice";
        }
        else if ($price == 'over20' || preg_match('/^over(\d+)$/', $price, $matches)) {
            // Support both legacy and new dynamic value formats
            $minPrice = isset($matches[1]) ? (int)$matches[1] : 40000;
            $query .= ($calories ? " AND" : " HAVING") . " (mk.preparation_price + ingredients_price) > $minPrice";
        }
    }
    
    // Add sorting
    switch ($sort) {
        case 'price_asc':
            $query .= " ORDER BY (mk.preparation_price + ingredients_price) ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY (mk.preparation_price + ingredients_price) DESC";
            break;
        case 'calories_asc':
            $query .= " ORDER BY base_calories ASC";
            break;
        case 'calories_desc':
            $query .= " ORDER BY base_calories DESC";
            break;
        default:
            $query .= " ORDER BY mk.name ASC";
    }
    
    // Execute query
    $result = $mysqli->query($query);
    
    if (!$result) {
        // If query failed, return error
        echo json_encode([
            'success' => false, 
            'message' => 'Database query failed: ' . $mysqli->error,
            'query' => $query // For debugging
        ]);
        exit;
    }
    
    // Build HTML
    $html = '';
    if($result->num_rows === 0) {
        $html = '<div class="col-12 text-center py-5"><h4>No meal kits match your filter criteria.</h4></div>'; 
    }
    while ($mealKit = $result->fetch_assoc()) {
        $total_price = $mealKit['preparation_price'] + $mealKit['ingredients_price'];
        
        // Check if meal kit is in user's favorites
        $is_favorite = false;
        if (isset($_SESSION['user_id'])) {
            $favorite_query = "SELECT 1 FROM user_favorites WHERE user_id = ? AND meal_kit_id = ?";
            $favorite_stmt = $mysqli->prepare($favorite_query);
            $favorite_stmt->bind_param("ii", $_SESSION['user_id'], $mealKit['meal_kit_id']);
            $favorite_stmt->execute();
            $is_favorite = $favorite_stmt->get_result()->num_rows > 0;
            $favorite_stmt->close();
        }
        
        $html .= '
<div class="col-md-6 col-lg-4 meal-kit-card" 
     data-category="' . $mealKit['category_id'] . '"
     data-calories="' . $mealKit['base_calories'] . '"
     data-price="' . $total_price . '">
    <div class="card h-100">
        <div class="position-relative">';
        
        // Get image URL
        $img_url = get_meal_kit_image_url($mealKit['image_url'], $mealKit['name']);
        $html .= '
            <img src="' . htmlspecialchars($img_url) . '" class="card-img-top" alt="Meal Kit Image">
            
            <button class="favorite-btn ' . ($is_favorite ? 'active' : '') . '" 
                    data-meal-kit-id="' . $mealKit['meal_kit_id'] . '" 
                    data-is-favorite="' . ($is_favorite ? 'true' : 'false') . '">
                <i class="bi ' . ($is_favorite ? 'bi-heart-fill' : 'bi-heart') . '"></i>
            </button>
        </div>
        <div class="card-body">
            <h5 class="card-title">' . htmlspecialchars($mealKit['name']) . '</h5>
            <p class="card-text">' . htmlspecialchars($mealKit['description']) . '</p>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary">' . htmlspecialchars($mealKit['category_name']) . '</span>
                <span class="badge bg-info">' . round($mealKit['base_calories']) . ' cal</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">' . number_format($total_price, 0) . ' MMK</h6>
                <div class="btn-group">
                    <a href="meal-details.php?id=' . $mealKit['meal_kit_id'] . '" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> View Details
                    </a>
                    <button class="btn btn-primary btn-sm" 
                            onclick="customizeMealKit(' . $mealKit['meal_kit_id'] . ')">
                        <i class="bi bi-cart-plus"></i> Customize
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>';
    }
    
    // Send response
    echo json_encode(['success' => true, 'html' => $html]);
}
else {
    // If not POST, return error
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}