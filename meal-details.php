<?php
session_start();
require_once 'config/connection.php';

// Helper function to get image URL from DB value (copied from meal-kits.php)
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/800x600/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0];
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get meal kit ID
$meal_kit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$meal_kit_id) {
    header("Location: meal-kits.php");
    exit();
}

// Fetch meal kit details
$stmt = $mysqli->prepare("
    SELECT mk.*, c.name as category_name
    FROM meal_kits mk
    LEFT JOIN categories c ON mk.category_id = c.category_id
    WHERE mk.meal_kit_id = ? AND mk.is_active = 1
");
$stmt->bind_param("i", $meal_kit_id);
$stmt->execute();
$meal_kit = $stmt->get_result()->fetch_assoc();

if (!$meal_kit) {
    $_SESSION['error_message'] = "The requested meal kit is not available.";
    header("Location: meal-kits.php");
    exit();
}

// Fetch ingredients with nutritional information
$stmt = $mysqli->prepare("
    SELECT i.*, mki.default_quantity
    FROM meal_kit_ingredients mki
    JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    WHERE mki.meal_kit_id = ?
");
$stmt->bind_param("i", $meal_kit_id);
$stmt->execute();
$ingredients = $stmt->get_result();

// Calculate total nutritional values (only from ingredients)
$total_calories = 0;
$total_protein = 0;
$total_carbs = 0;
$total_fat = 0;
$total_ingredients_price = 0;
$preparation_price = $meal_kit['preparation_price'];
$ingredients_list = [];

while ($ingredient = $ingredients->fetch_assoc()) {
    // Calculate values directly from per 100g values and quantity
    $quantity = $ingredient['default_quantity'];
    $calories = ($ingredient['calories_per_100g'] * $quantity) / 100;
    $protein = ($ingredient['protein_per_100g'] * $quantity) / 100;
    $carbs = ($ingredient['carbs_per_100g'] * $quantity) / 100;
    $fat = ($ingredient['fat_per_100g'] * $quantity) / 100;
    $price = ($ingredient['price_per_100g'] * $quantity) / 100;

    // Add to totals
    $total_calories += $calories;
    $total_protein += $protein;
    $total_carbs += $carbs;
    $total_fat += $fat;
    $total_ingredients_price += $price;

    // Store calculated values with the ingredient
    $ingredient['calculated_calories'] = $calories;
    $ingredient['calculated_protein'] = $protein;
    $ingredient['calculated_carbs'] = $carbs;
    $ingredient['calculated_fat'] = $fat;
    $ingredient['calculated_price'] = $price;
    
    $ingredients_list[] = $ingredient;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meal_kit['name']); ?> - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Include meal-customization.js in the head -->
    <script src="assets/js/meal-customization.js"></script>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row">
            <!-- Meal Kit Image and Basic Info -->
            <div class="col-lg-6 mb-4">
                <?php $img_url = get_meal_kit_image_url($meal_kit['image_url'], $meal_kit['name']); ?>
                <img src="<?php echo htmlspecialchars($img_url); ?>" class="img-fluid rounded mb-4" alt="Meal Kit Image">

                <div class="card mb-4">
                    <div class="card-body">
                        <h1 class="card-title"><?php echo htmlspecialchars($meal_kit['name']); ?></h1>
                        <p class="text-muted mb-3">
                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($meal_kit['category_name']); ?>
                        </p>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($meal_kit['description'])); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <button class="btn btn-primary btn-lg"
                                onclick="customizeMealKit(<?php echo $meal_kit_id; ?>)">
                                <i class="bi bi-cart-plus"></i> Customize & Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nutritional Information and Ingredients -->
            <div class="col-lg-6">
                <!-- Nutritional Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Nutritional Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 col-md-3 mb-3">
                                <div class="nutrition-item">
                                    <h6 class="text-primary">Calories</h6>
                                    <p class="mb-0"><?php echo round($total_calories); ?></p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="nutrition-item">
                                    <h6 class="text-success">Protein</h6>
                                    <p class="mb-0"><?php echo round($total_protein, 1); ?>g</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="nutrition-item">
                                    <h6 class="text-warning">Carbs</h6>
                                    <p class="mb-0"><?php echo round($total_carbs, 1); ?>g</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="nutrition-item">
                                    <h6 class="text-danger">Fat</h6>
                                    <p class="mb-0"><?php echo round($total_fat, 1); ?>g</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Health Tips Section -->
                <div class="card mb-4">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Health Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php if ($total_protein > 20): ?>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                High in protein - great for muscle building and recovery!
                            </li>
                            <?php endif; ?>
                            <?php if ($total_fat < 15): ?>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Low in fat - helps maintain a healthy weight
                            </li>
                            <?php endif; ?>
                            <?php if ($total_carbs < 50): ?>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Moderate in carbs - provides sustained energy
                            </li>
                            <?php endif; ?>
                            <?php if ($total_calories < 600): ?>
                            <li class="list-group-item">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Balanced calorie count - fits well in a healthy diet
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Ingredients List -->
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0">Ingredients</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Quantity</th>
                                        <th>Calories</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ingredients_list as $ingredient): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($ingredient['name']); ?>
                                            <small
                                                class="text-muted d-block"><?php echo round($ingredient['calories_per_100g']); ?>
                                                cal per 100g</small>
                                            <?php if ($ingredient['is_vegetarian']): ?>
                                            <span class="badge bg-success">Veg</span>
                                            <?php endif; ?>
                                            <?php if ($ingredient['is_vegan']): ?>
                                            <span class="badge bg-info">Vegan</span>
                                            <?php endif; ?>
                                            <?php if ($ingredient['is_halal']): ?>
                                            <span class="badge bg-primary">Halal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $ingredient['default_quantity']; ?>g</td>
                                        <td><?php echo round($ingredient['calculated_calories']); ?> cal</td>
                                        <td>$<?php echo number_format($ingredient['calculated_price'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Ingredients Total</strong></td>
                                        <td></td>
                                        <td><strong><?php echo round($total_calories); ?> cal</strong></td>
                                        <td><strong>$<?php echo number_format($total_ingredients_price, 2); ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Preparation Fee</strong></td>
                                        <td></td>
                                        <td></td>
                                        <td><strong>$<?php echo number_format($preparation_price, 2); ?></strong></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td><strong>Total Price</strong></td>
                                        <td></td>
                                        <td></td>
                                        <td><strong>$<?php echo number_format($total_ingredients_price + $preparation_price, 2); ?></strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customization Modal -->
    <div class="modal fade" id="customizeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customize Your Meal Kit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customizeContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Include Toast Notifications -->
    <?php include 'includes/toast-notifications.php'; ?>

    <!-- Bootstrap JS -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
    
    <!-- Custom JS -->
    <script>
    // Initialize cart count from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        updateCartCountFromStorage();
    });
    
    function customizeMealKit(mealKitId) {
        fetch(`api/meal-kits/get_customization.php?meal_kit_id=${mealKitId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('customizeContent').innerHTML = data.html;
                    var modal = new bootstrap.Modal(document.getElementById('customizeModal'));
                    modal.show();
                    
                    // Initialize listeners after content is loaded
                    initializeQuantityListeners();
                } else {
                    // Show error message
                    document.getElementById('errorToastMessage').textContent = data.message || 'Failed to load customization options';
                    const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                    toast.show();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('errorToastMessage').textContent = 'An error occurred while loading customization options';
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
            });
    }

    // Use the standardized addToCart function from meal-customization.js
    </script>
</body>

</html>