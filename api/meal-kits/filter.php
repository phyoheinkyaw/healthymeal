<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get filter parameters
$data = json_decode(file_get_contents('php://input'), true);
$category = $data['category'] ?? '';
$dietary = $data['dietary'] ?? '';
$calories = $data['calories'] ?? '';
$sort = $data['sort'] ?? 'name';

// Build query
$query = "
    SELECT mk.*, c.name as category_name,
           SUM(i.calories_per_100g * mki.default_quantity / 100) as base_calories,
           SUM(i.price_per_100g * mki.default_quantity / 100) as ingredients_price
    FROM meal_kits mk
    LEFT JOIN categories c ON mk.category_id = c.category_id
    LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
    LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    WHERE 1=1
";

$params = [];
$types = "";

// Add filters
if ($category) {
    $query .= " AND mk.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

if ($dietary) {
    $query .= " AND NOT EXISTS (
        SELECT 1 FROM meal_kit_ingredients mki2
        JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
        WHERE mki2.meal_kit_id = mk.meal_kit_id
        AND (
            CASE 
                WHEN ? = 'vegetarian' THEN i2.is_vegetarian = 0
                WHEN ? = 'vegan' THEN i2.is_vegan = 0
                WHEN ? = 'halal' THEN i2.is_halal = 0
                ELSE 0
            END
        )
    )";
    $params[] = $dietary;
    $params[] = $dietary;
    $params[] = $dietary;
    $types .= "sss";
}

// Group by meal kit
$query .= " GROUP BY mk.meal_kit_id";

// Add calorie filter
if ($calories) {
    switch ($calories) {
        case 'under500':
            $query .= " HAVING base_calories < 500";
            break;
        case '500-800':
            $query .= " HAVING base_calories BETWEEN 500 AND 800";
            break;
        case 'over800':
            $query .= " HAVING base_calories > 800";
            break;
    }
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY mk.preparation_price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY mk.preparation_price DESC";
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

// Prepare and execute query
$stmt = $mysqli->prepare($query);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Build HTML response
$html = '';
if($result->num_rows === 0) {
    $html = '<h4>No meal kits found.</h4>'; 
}
while ($mealKit = $result->fetch_assoc()) {
    $html .= '
<div class="col-md-6 col-lg-4 meal-kit-card" data-category="' . htmlspecialchars($mealKit['category_id']) . '"
    data-calories="' . htmlspecialchars($mealKit['base_calories']) . '">
    <div class="card h-100">
        ' . ($mealKit['image_url']
        ? '<img src="' . htmlspecialchars($mealKit['image_url']) . '" class="card-img-top"
            alt="' . htmlspecialchars($mealKit['name']) . '">'
        : '<img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($mealKit['name']) . '">') . '

        <div class="card-body">
            <h5 class="card-title">' . htmlspecialchars($mealKit['name']) . '</h5>
            <p class="card-text">' . htmlspecialchars($mealKit['description']) . '</p>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-primary">' . htmlspecialchars($mealKit['category_name']) . '</span>
                <span class="badge bg-info">' . round($mealKit['base_calories']) . ' cal</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">$' . number_format($mealKit['preparation_price'] + $mealKit['ingredients_price'], 2) . '</h6>
                <div class="btn-group">
                    <a href="meal-details.php?id=' . htmlspecialchars($mealKit['meal_kit_id']) . '"
                        class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye"></i> View Details
                    </a>
                    <button class="btn btn-primary btn-sm"
                        onclick="customizeMealKit(' . htmlspecialchars($mealKit['meal_kit_id']) . ')">
                        <i class="bi bi-cart-plus"></i> Customize
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>';
}

echo json_encode([
'success' => true,
'html' => $html
]);