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

$user_id = $_SESSION['user_id'];

// Get meal kit ID
$meal_kit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$meal_kit_id) {
    header("Location: meal-kits.php");
    exit();
}

// Handle add/remove favorite action via AJAX
if (isset($_POST['favorite_action'])) {
    $action = $_POST['favorite_action'];
    
    if ($action === 'add') {
        // Add to favorites
        $insert_query = "INSERT IGNORE INTO user_favorites (user_id, meal_kit_id) VALUES (?, ?)";
        $stmt = $mysqli->prepare($insert_query);
        $stmt->bind_param("ii", $user_id, $meal_kit_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'is_favorite' => true]);
    } elseif ($action === 'remove') {
        // Remove from favorites
        $delete_query = "DELETE FROM user_favorites WHERE user_id = ? AND meal_kit_id = ?";
        $stmt = $mysqli->prepare($delete_query);
        $stmt->bind_param("ii", $user_id, $meal_kit_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'is_favorite' => false]);
    }
    
    exit;
}

// Check if meal kit is in user's favorites
$favorite_query = "SELECT 1 FROM user_favorites WHERE user_id = ? AND meal_kit_id = ?";
$favorite_stmt = $mysqli->prepare($favorite_query);
$favorite_stmt->bind_param("ii", $user_id, $meal_kit_id);
$favorite_stmt->execute();
$is_favorite = $favorite_stmt->get_result()->num_rows > 0;
$favorite_stmt->close();

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
    <!-- Slick Carousel CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Include meal-customization.js in the head -->
    <script src="assets/js/meal-customization.js"></script>
    <style>
        .favorite-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }
        
        .favorite-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .favorite-btn i {
            font-size: 20px;
            color: #FF6B35;
        }
        
        .favorite-btn.active {
            background-color: #FF6B35;
        }
        
        .favorite-btn.active i {
            color: white;
        }
        
        .meal-img-container {
            position: relative;
        }
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row">
            <!-- Meal Kit Image and Basic Info -->
            <div class="col-lg-6 mb-4">
                <div class="meal-img-container">
                    <?php $img_url = get_meal_kit_image_url($meal_kit['image_url'], $meal_kit['name']); ?>
                    <img src="<?php echo htmlspecialchars($img_url); ?>" class="img-fluid rounded mb-4" alt="Meal Kit Image">
                    
                    <button id="favoriteBtn" class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-meal-kit-id="<?php echo $meal_kit_id; ?>" data-is-favorite="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                        <i class="bi <?php echo $is_favorite ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                    </button>
                </div>

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
                                            <?php if ($ingredient['is_vegetarian'] == 1): ?>
                                            <span class="badge bg-success">Veg</span>
                                            <?php endif; ?>
                                            <?php if ($ingredient['is_vegan'] == 1): ?>
                                            <span class="badge bg-info">Vegan</span>
                                            <?php endif; ?>
                                            <?php if ($ingredient['is_halal'] == 1): ?>
                                            <span class="badge bg-primary">Halal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $ingredient['default_quantity']; ?>g</td>
                                        <td><?php echo round($ingredient['calculated_calories']); ?> cal</td>
                                        <td><?php echo number_format($ingredient['calculated_price'], 0); ?> MMK</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Ingredients Total</strong></td>
                                        <td></td>
                                        <td><strong><?php echo round($total_calories); ?> cal</strong></td>
                                        <td><strong><?php echo number_format($total_ingredients_price, 0); ?> MMK</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Preparation Fee</strong></td>
                                        <td></td>
                                        <td></td>
                                        <td><strong><?php echo number_format($preparation_price, 0); ?> MMK</strong></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td><strong>Total Price</strong></td>
                                        <td></td>
                                        <td></td>
                                        <td><strong><?php echo number_format($total_ingredients_price + $preparation_price, 0); ?> MMK</strong>
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

    <!-- Similar Meal Kits Section -->
    <?php
    // First, determine dietary preferences of the current meal kit
    $dietary_query = $mysqli->prepare("
        SELECT 
            SUM(CASE WHEN i.is_vegetarian = 0 THEN 1 ELSE 0 END) as non_vegetarian_count,
            SUM(CASE WHEN i.is_vegan = 0 THEN 1 ELSE 0 END) as non_vegan_count,
            SUM(CASE WHEN i.is_halal = 0 THEN 1 ELSE 0 END) as non_halal_count
        FROM meal_kit_ingredients mki
        JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
        WHERE mki.meal_kit_id = ?
    ");
    $dietary_query->bind_param("i", $meal_kit_id);
    $dietary_query->execute();
    $dietary_result = $dietary_query->get_result()->fetch_assoc();
    
    // Determine if this meal is vegetarian, vegan, or halal
    $is_vegetarian = ($dietary_result['non_vegetarian_count'] == 0);
    $is_vegan = ($dietary_result['non_vegan_count'] == 0);
    $is_halal = ($dietary_result['non_halal_count'] == 0);
    
    // Build query to find similar meal kits based on dietary preferences
    $query = "
        SELECT mk.*, c.name as category_name,
               SUM(i.calories_per_100g * mki.default_quantity / 100) as base_calories,
               SUM(i.price_per_100g * mki.default_quantity / 100) as ingredients_price
        FROM meal_kits mk
        LEFT JOIN categories c ON mk.category_id = c.category_id
        LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
        LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
        WHERE mk.is_active = 1 
        AND mk.meal_kit_id != ?
    ";
    
    $params = [$meal_kit_id];
    $types = "i";
    
    // Add dietary conditions
    $conditions = [];
    
    if ($is_vegetarian) {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM meal_kit_ingredients mki2
            JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
            WHERE mki2.meal_kit_id = mk.meal_kit_id
            AND i2.is_vegetarian = 0
        )";
    }
    
    if ($is_vegan) {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM meal_kit_ingredients mki2
            JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
            WHERE mki2.meal_kit_id = mk.meal_kit_id
            AND i2.is_vegan = 0
        )";
    }
    
    if ($is_halal) {
        $conditions[] = "NOT EXISTS (
            SELECT 1 FROM meal_kit_ingredients mki2
            JOIN ingredients i2 ON mki2.ingredient_id = i2.ingredient_id
            WHERE mki2.meal_kit_id = mk.meal_kit_id
            AND i2.is_halal = 0
        )";
    }
    
    // Also add condition for same category
    $conditions[] = "mk.category_id = ?";
    $params[] = $meal_kit['category_id'];
    $types .= "i";
    
    if (!empty($conditions)) {
        $query .= " AND (" . implode(" OR ", $conditions) . ")";
    }
    
    $query .= " GROUP BY mk.meal_kit_id ORDER BY RAND() LIMIT 10";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $similar_meal_kits = $stmt->get_result();
    
    // Determine the title based on dietary preferences
    $similar_title = "Similar Meal Kits";
    if ($is_vegan) {
        $similar_title = "Similar Vegan Meal Kits";
    } elseif ($is_vegetarian) {
        $similar_title = "Similar Vegetarian Meal Kits";
    } elseif ($is_halal) {
        $similar_title = "Similar Halal Meal Kits";
    }
    
    // If we have similar meal kits, display them
    if ($similar_meal_kits->num_rows > 0):
    ?>
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="mb-4"><?php echo $similar_title; ?></h2>
            
            <div class="similar-meal-kits-slider">
                <?php while ($similar = $similar_meal_kits->fetch_assoc()): 
                    $total_price = $similar['preparation_price'] + ($similar['ingredients_price'] ?? 0);
                ?>
                <div class="px-2">
                    <div class="card h-100 shadow-sm">
                        <?php $similar_img_url = get_meal_kit_image_url($similar['image_url'], $similar['name']); ?>
                        <img src="<?php echo htmlspecialchars($similar_img_url); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($similar['name']); ?>" style="height: 180px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($similar['name']); ?></h5>
                            <div class="mb-2">
                                <?php if ($is_vegetarian): ?>
                                <span class="badge bg-success me-1">Vegetarian</span>
                                <?php endif; ?>
                                <?php if ($is_vegan): ?>
                                <span class="badge bg-info me-1">Vegan</span>
                                <?php endif; ?>
                                <?php if ($is_halal): ?>
                                <span class="badge bg-primary">Halal</span>
                                <?php endif; ?>
                            </div>
                            <p class="card-text small mb-3"><?php echo mb_strimwidth(htmlspecialchars($similar['description']), 0, 80, "..."); ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <strong class="text-primary"><?php echo number_format($total_price, 0); ?> MMK</strong>
                                <a href="meal-details.php?id=<?php echo $similar['meal_kit_id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    
    <!-- Custom CSS for Slick Carousel -->
    <style>
        /* Slick Slider Customization */
        .similar-meal-kits-slider .slick-prev,
        .similar-meal-kits-slider .slick-next {
            width: 40px;
            height: 40px;
            background-color: var(--primary);
            border-radius: 50%;
            z-index: 1;
        }
        
        .similar-meal-kits-slider .slick-prev {
            left: -20px;
        }
        
        .similar-meal-kits-slider .slick-next {
            right: -20px;
        }
        
        .similar-meal-kits-slider .slick-prev:before,
        .similar-meal-kits-slider .slick-next:before {
            font-family: 'bootstrap-icons' !important;
            font-size: 20px;
            color: white;
            opacity: 1;
        }
        
        .similar-meal-kits-slider .slick-prev:before {
            content: '\f284';
        }
        
        .similar-meal-kits-slider .slick-next:before {
            content: '\f285';
        }
        
        .similar-meal-kits-slider .slick-dots {
            bottom: -40px;
        }
        
        .similar-meal-kits-slider .slick-dots li button:before {
            font-size: 12px;
            color: var(--primary);
            opacity: 0.5;
        }
        
        .similar-meal-kits-slider .slick-dots li.slick-active button:before {
            opacity: 1;
        }
        
        /* Card hover effects */
        .similar-meal-kits-slider .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin: 10px;
        }
        
        .similar-meal-kits-slider .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Add space for dots */
        .similar-meal-kits-slider {
            padding-bottom: 50px;
        }
    </style>
    <?php endif; ?>

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
    </script>

    <!-- Slick Carousel JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script>
        $(document).ready(function(){
            $('.similar-meal-kits-slider').slick({
                slidesToShow: 4,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                dots: true,
                arrows: true,
                infinite: true,
                responsive: [
                    {
                        breakpoint: 1200,
                        settings: {
                            slidesToShow: 3,
                            slidesToScroll: 1
                        }
                    },
                    {
                        breakpoint: 992,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1
                        }
                    },
                    {
                        breakpoint: 576,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
            
            // Favorite button functionality
            $('#favoriteBtn').on('click', function() {
                const btn = $(this);
                const mealKitId = btn.data('meal-kit-id');
                const isFavorite = btn.data('is-favorite') === true || btn.data('is-favorite') === 'true';
                const action = isFavorite ? 'remove' : 'add';
                
                $.ajax({
                    url: 'meal-details.php?id=' + mealKitId,
                    type: 'POST',
                    data: {
                        favorite_action: action
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update button state
                            if (response.is_favorite) {
                                btn.addClass('active');
                                btn.find('i').removeClass('bi-heart').addClass('bi-heart-fill');
                                btn.data('is-favorite', true);
                                
                                // Show success toast
                                $('#successToastMessage').text('Added to favorites!');
                                const toast = new bootstrap.Toast(document.getElementById('successToast'));
                                toast.show();
                            } else {
                                btn.removeClass('active');
                                btn.find('i').removeClass('bi-heart-fill').addClass('bi-heart');
                                btn.data('is-favorite', false);
                                
                                // Show info toast
                                $('#infoToastMessage').text('Removed from favorites');
                                const toast = new bootstrap.Toast(document.getElementById('infoToast'));
                                toast.show();
                            }
                        }
                    },
                    error: function() {
                        // Show error toast
                        $('#errorToastMessage').text('Failed to update favorites');
                        const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                        toast.show();
                    }
                });
            });
        });
    </script>
</body>

</html>