<?php
session_start();
require_once 'config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user preferences
$stmt = $mysqli->prepare("
    SELECT dietary_restrictions, allergies, calorie_goal 
    FROM user_preferences 
    WHERE user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$userPrefs = $result->fetch_assoc();

// Set default values if user has no preferences
if (!$userPrefs) {
    $userPrefs = [
        'dietary_restrictions' => '',
        'allergies' => '',
        'calorie_goal' => 0
    ];
}

// Fetch categories for filter
$categories = $mysqli->query("SELECT * FROM categories ORDER BY name");

// Get meal kits stats for dynamic filters
$mealKitStats = $mysqli->query("
    SELECT 
        MIN(mk.base_calories) as min_calories,
        MAX(mk.base_calories) as max_calories,
        MIN(mk.preparation_price + COALESCE(ingredient_prices.total_price, 0)) as min_price,
        MAX(mk.preparation_price + COALESCE(ingredient_prices.total_price, 0)) as max_price
    FROM meal_kits mk
    LEFT JOIN (
        SELECT 
            mki.meal_kit_id,
            SUM(i.price_per_100g * mki.default_quantity / 100) as total_price
        FROM meal_kit_ingredients mki
        JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
        GROUP BY mki.meal_kit_id
    ) as ingredient_prices ON mk.meal_kit_id = ingredient_prices.meal_kit_id
    WHERE mk.is_active = 1
")->fetch_assoc();

// Set dynamic calorie ranges based on actual data
$calorieRanges = [];
if ($mealKitStats) {
    $min_cal = floor($mealKitStats['min_calories'] / 100) * 100; // Round down to nearest 100
    $max_cal = ceil($mealKitStats['max_calories'] / 100) * 100;  // Round up to nearest 100
    
    if ($max_cal - $min_cal > 600) {
        $mid_cal = $min_cal + round(($max_cal - $min_cal) / 2 / 100) * 100;
        $calorieRanges = [
            "under{$mid_cal}" => "Under {$mid_cal} cal",
            "{$mid_cal}-{$max_cal}" => "{$mid_cal}-{$max_cal} cal",
            "over{$max_cal}" => "Over {$max_cal} cal"
        ];
    } else {
        $mid_cal = $min_cal + 300;
        $calorieRanges = [
            "under{$mid_cal}" => "Under {$mid_cal} cal",
            "over{$mid_cal}" => "Over {$mid_cal} cal"
        ];
    }
}
// Fallback if no stats available
if (empty($calorieRanges)) {
    $calorieRanges = [
        "under500" => "Under 500 cal",
        "500-800" => "500-800 cal",
        "over800" => "Over 800 cal"
    ];
}

// Set dynamic price ranges based on actual data
$priceRanges = [];
if ($mealKitStats) {
    $min_price = floor($mealKitStats['min_price'] / 5000) * 5000; // Round down to nearest 5000
    $max_price = ceil($mealKitStats['max_price'] / 5000) * 5000;  // Round up to nearest 5000
    
    if ($max_price - $min_price > 20000) {
        $mid_price = $min_price + round(($max_price - $min_price) / 2 / 5000) * 5000;
        $priceRanges = [
            "under{$mid_price}" => "Under " . number_format($mid_price) . " MMK",
            "{$mid_price}-{$max_price}" => number_format($mid_price) . " - " . number_format($max_price) . " MMK",
            "over{$max_price}" => "Over " . number_format($max_price) . " MMK"
        ];
    } else {
        $mid_price = $min_price + 10000;
        $priceRanges = [
            "under{$mid_price}" => "Under " . number_format($mid_price) . " MMK",
            "over{$mid_price}" => "Over " . number_format($mid_price) . " MMK"
        ];
    }
}
// Fallback if no stats available
if (empty($priceRanges)) {
    $priceRanges = [
        "under20000" => "Under 20,000 MMK",
        "20000-40000" => "20,000 - 40,000 MMK",
        "over40000" => "Over 40,000 MMK"
    ];
}

// Fetch all meal kits with their base calories and ingredient prices
$mealKits = $mysqli->query("
    SELECT mk.*, c.name as category_name,
           SUM(i.calories_per_100g * mki.default_quantity / 100) as base_calories,
           SUM(i.price_per_100g * mki.default_quantity / 100) as ingredients_price
    FROM meal_kits mk
    LEFT JOIN categories c ON mk.category_id = c.category_id
    LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
    LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    WHERE mk.is_active = 1
    GROUP BY mk.meal_kit_id
");

// Helper function to get image URL from DB value (copied from admin/meal-kits.php)
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Kits - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <!-- Filters Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Filters</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php while ($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Dietary Restriction</label>
                                <select class="form-select" id="dietaryFilter">
                                    <option value="">All</option>
                                    <option value="meat"
                                        <?php echo ($userPrefs['dietary_restrictions'] == 'meat') ? 'selected' : ''; ?>>
                                        Contains Meat</option>
                                    <option value="vegetarian"
                                        <?php echo ($userPrefs['dietary_restrictions'] == 'vegetarian') ? 'selected' : ''; ?>>
                                        Vegetarian</option>
                                    <option value="vegan"
                                        <?php echo ($userPrefs['dietary_restrictions'] == 'vegan') ? 'selected' : ''; ?>>
                                        Vegan</option>
                                    <option value="halal"
                                        <?php echo ($userPrefs['dietary_restrictions'] == 'halal') ? 'selected' : ''; ?>>
                                        Halal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Calorie Range</label>
                                <select class="form-select" id="calorieFilter">
                                    <option value="">All</option>
                                    <?php foreach ($calorieRanges as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Price Range</label>
                                <select class="form-select" id="priceFilter">
                                    <option value="">All Prices</option>
                                    <?php foreach ($priceRanges as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sort By</label>
                                <select class="form-select" id="sortBy">
                                    <option value="name">Name</option>
                                    <option value="price_asc">Price: Low to High</option>
                                    <option value="price_desc">Price: High to Low</option>
                                    <option value="calories_asc">Calories: Low to High</option>
                                    <option value="calories_desc">Calories: High to Low</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Meal Kits Grid -->
        <div class="row g-4" id="mealKitsGrid">
            <?php 
            // Store all meal kits in an array
            $meal_kits_array = [];
            while ($mealKit = $mealKits->fetch_assoc()) {
                $meal_kits_array[] = $mealKit;
            }
            
            // Display only first 9 meal kits initially
            $total_meal_kits = count($meal_kits_array);
            $visible_count = min(9, $total_meal_kits);
            
            for($i = 0; $i < $visible_count; $i++): 
                $mealKit = $meal_kits_array[$i];
            ?>
            <div class="col-md-6 col-lg-4 meal-kit-card" 
                data-category="<?php echo $mealKit['category_id']; ?>"
                data-calories="<?php echo $mealKit['base_calories']; ?>"
                data-price="<?php echo $mealKit['preparation_price']+$mealKit['ingredients_price']; ?>">
                <div class="card h-100">
                    <div class="position-relative">
                        <?php $img_url = get_meal_kit_image_url($mealKit['image_url'], $mealKit['name']); ?>
                        <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top" alt="Meal Kit Image">
                        
                        <?php
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
                        ?>
                        
                        <button class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" 
                                data-meal-kit-id="<?php echo $mealKit['meal_kit_id']; ?>" 
                                data-is-favorite="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                            <i class="bi <?php echo $is_favorite ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                        </button>
                    </div>

                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($mealKit['name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($mealKit['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span
                                class="badge bg-primary"><?php echo htmlspecialchars($mealKit['category_name']); ?></span>
                            <span class="badge bg-info"><?php echo round($mealKit['base_calories']); ?> cal</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <?php echo number_format($mealKit['preparation_price']+$mealKit['ingredients_price'], 0); ?> MMK
                            </h6>
                            <div class="btn-group">
                                <a href="meal-details.php?id=<?php echo $mealKit['meal_kit_id']; ?>"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <button class="btn btn-primary btn-sm"
                                    onclick="customizeMealKit(<?php echo $mealKit['meal_kit_id']; ?>)">
                                    <i class="bi bi-cart-plus"></i> Customize
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <?php if($total_meal_kits > 9): ?>
        <div class="row mt-4">
            <div class="col-12 text-center">
                <button id="loadMoreBtn" class="btn btn-primary" data-visible="9" data-total="<?php echo $total_meal_kits; ?>">
                    Load More
                </button>
            </div>
        </div>
            
        <!-- Hidden template for remaining meal kits -->
        <div id="remainingMealKits" style="display: none;">
            <?php for($i = 9; $i < $total_meal_kits; $i++): 
                $mealKit = $meal_kits_array[$i];
            ?>
            <div class="col-md-6 col-lg-4 meal-kit-card" 
                data-index="<?php echo $i; ?>"
                data-category="<?php echo $mealKit['category_id']; ?>"
                data-calories="<?php echo $mealKit['base_calories']; ?>"
                data-price="<?php echo $mealKit['preparation_price']+$mealKit['ingredients_price']; ?>">
                <div class="card h-100">
                    <div class="position-relative">
                        <?php $img_url = get_meal_kit_image_url($mealKit['image_url'], $mealKit['name']); ?>
                        <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top" alt="Meal Kit Image">
                        
                        <?php
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
                        ?>
                        
                        <button class="favorite-btn <?php echo $is_favorite ? 'active' : ''; ?>" 
                                data-meal-kit-id="<?php echo $mealKit['meal_kit_id']; ?>" 
                                data-is-favorite="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                            <i class="bi <?php echo $is_favorite ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                        </button>
                    </div>

                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($mealKit['name']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($mealKit['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span
                                class="badge bg-primary"><?php echo htmlspecialchars($mealKit['category_name']); ?></span>
                            <span class="badge bg-info"><?php echo round($mealKit['base_calories']); ?> cal</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <?php echo number_format($mealKit['preparation_price']+$mealKit['ingredients_price'], 0); ?> MMK
                            </h6>
                            <div class="btn-group">
                                <a href="meal-details.php?id=<?php echo $mealKit['meal_kit_id']; ?>"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                <button class="btn btn-primary btn-sm"
                                    onclick="customizeMealKit(<?php echo $mealKit['meal_kit_id']; ?>)">
                                    <i class="bi bi-cart-plus"></i> Customize
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Customization Modal -->
    <div class="modal fade" id="customizeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customize Your Meal Kit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="customizeContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Include Toast Notifications -->
    <?php include 'includes/toast-notifications.php'; ?>

    <!-- jQuery first, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables to store filtered items and state
        var filteredItems = [];
        var currentFilters = {
            category: '',
            dietary: '',
            calories: '',
            price: '',
            sort: 'name'
        };
        var itemsPerPage = 9; // Initial load
        var loadMoreCount = 6; // Items to load on each "Load More" click
        var currentlyDisplayed = 0;

        // Wait for document to be ready before initializing
        $(document).ready(function(){
            // First try to get cart count from database via API
            fetchCartCountFromDatabase();
            
            // Add event listeners to filters
            document.getElementById('categoryFilter').addEventListener('change', applyUserFilters);
            document.getElementById('dietaryFilter').addEventListener('change', applyUserFilters);
            document.getElementById('calorieFilter').addEventListener('change', applyUserFilters);
            document.getElementById('priceFilter').addEventListener('change', applyUserFilters);
            document.getElementById('sortBy').addEventListener('change', applyUserFilters);
            
            // Initialize favorite buttons
            initializeFavoriteButtons();
            
            // Initialize the filtered items with the initial meal kits in the DOM
            initializeFilteredItems();
            
            // Initialize load more functionality if button exists
            initializeLoadMore();
        });
        
        // Initialize filteredItems with the initial meal kits in the DOM
        function initializeFilteredItems() {
            // Get the initial visible items
            currentlyDisplayed = $('#mealKitsGrid .meal-kit-card').length;
            
            // Get all items (visible and hidden)
            filteredItems = $('#mealKitsGrid .meal-kit-card').toArray();
            
            // Add the hidden items from the remainingMealKits div
            if ($('#remainingMealKits').length > 0) {
                var hiddenItems = $('#remainingMealKits .meal-kit-card').toArray();
                filteredItems = filteredItems.concat(hiddenItems);
            }
            
            // Update the load more button state
            updateLoadMoreButton();
        }
        
        // Function to handle user-initiated filter changes
        function applyUserFilters() {
            applyFilters(true);
        }
        
        // Initialize Load More Functionality
        function initializeLoadMore() {
            $('#loadMoreBtn').off('click').on('click', function() {
                loadMoreItems();
            });
        }
        
        // Function to load more items from stored filtered items
        function loadMoreItems() {
            var totalItems = filteredItems.length;
            var remainingItems = totalItems - currentlyDisplayed;
            
            if (remainingItems <= 0) return;
            
            var toLoad = Math.min(loadMoreCount, remainingItems);
            var gridContainer = $('#mealKitsGrid');
            
            // Append the next batch of items
            for (var i = 0; i < toLoad; i++) {
                var itemIndex = currentlyDisplayed + i;
                if (itemIndex < totalItems) {
                    // Clone the item if it's from the hidden container
                    var item = $(filteredItems[itemIndex]);
                    if (item.parent().attr('id') === 'remainingMealKits') {
                        item = item.clone();
                    }
                    gridContainer.append(item);
                }
            }
            
            // Update counters
            currentlyDisplayed += toLoad;
            
            // Update button text and state
            updateLoadMoreButton();
            
            // Re-initialize components for newly added items
            initializeFavoriteButtons();
        }
        
        // Update the Load More button state and text
        function updateLoadMoreButton() {
            var btn = $('#loadMoreBtn');
            if (!btn.length) return;
            
            var totalItems = filteredItems.length;
            var remaining = totalItems - currentlyDisplayed;
            
            if (remaining > 0) {
                btn.text('Load More (' + remaining + ' remaining)');
                btn.removeClass('disabled').show();
            } else {
                btn.text('All meal kits loaded');
                btn.addClass('disabled');
            }
        }
        
        // Initialize favorite buttons
        function initializeFavoriteButtons() {
            $('.favorite-btn').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const btn = $(this);
                const mealKitId = btn.data('meal-kit-id');
                const isFavorite = btn.data('is-favorite') === true || btn.data('is-favorite') === 'true';
                const action = isFavorite ? 'remove' : 'add';
                
                // Send AJAX request to favorites.php
                $.ajax({
                    url: 'favorites.php',
                    type: 'POST',
                    data: {
                        action: action,
                        meal_kit_id: mealKitId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update button state
                            if (action === 'add') {
                                btn.addClass('active');
                                btn.find('i').removeClass('bi-heart').addClass('bi-heart-fill');
                                btn.data('is-favorite', true);
                                
                                // Show toast
                                $('#successToastMessage').text('Added to favorites!');
                                const successToast = new bootstrap.Toast(document.getElementById('successToast'));
                                successToast.show();
                            } else {
                                btn.removeClass('active');
                                btn.find('i').removeClass('bi-heart-fill').addClass('bi-heart');
                                btn.data('is-favorite', false);
                                
                                // Show toast
                                $('#infoToastMessage').text('Removed from favorites');
                                const infoToast = new bootstrap.Toast(document.getElementById('infoToast'));
                                infoToast.show();
                            }
                            
                            // Update favorites count in navbar
                            updateFavoritesCount();
                        }
                    },
                    error: function() {
                        // Show error toast
                        $('#errorToastMessage').text('Failed to update favorites');
                        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));
                        errorToast.show();
                    }
                });
            });
        }
        
        // Function to update favorites count in navbar
        function updateFavoritesCount() {
            fetch('api/favorites/get_favorites_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const favoritesBadge = document.getElementById('favoritesCount');
                        if (favoritesBadge) {
                            favoritesBadge.textContent = data.count;
                            favoritesBadge.style.display = data.count > 0 ? 'inline-block' : 'none';
                        }
                    }
                });
        }
        
        // Function to fetch cart count from database
        function fetchCartCountFromDatabase() {
            fetch('api/cart/get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the cart badge with count from database
                        updateCartBadge(data.count);
                    } else {
                        // If API call fails, fall back to localStorage
                        updateCartCountFromStorage();
                    }
                })
                .catch(error => {
                    console.error('Error fetching cart count:', error);
                    // If fetch fails, fall back to localStorage
                    updateCartCountFromStorage();
                });
        }
        
        // Function to customize meal kit
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

        function applyFilters(isUserInitiated = false) {
            // Update currentFilters
            currentFilters.category = document.getElementById('categoryFilter').value;
            currentFilters.dietary = document.getElementById('dietaryFilter').value;
            currentFilters.calories = document.getElementById('calorieFilter').value;
            currentFilters.price = document.getElementById('priceFilter').value;
            currentFilters.sort = document.getElementById('sortBy').value;
            
            // If all filters are default/empty and it's not a user-initiated filter,
            // don't make the API call to avoid hiding the load more button on page load
            if (!isUserInitiated && 
                currentFilters.category === '' && 
                currentFilters.dietary === '' && 
                currentFilters.calories === '' && 
                currentFilters.price === '' && 
                currentFilters.sort === 'name') {
                return;
            }
            
            // Show loading state
            const grid = document.getElementById('mealKitsGrid');
            grid.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading meal kits...</p></div>';

            // Hide load more button during loading
            $('#loadMoreBtn').hide();

            // Validate dietary value to prevent issues
            const validDietary = ['', 'vegetarian', 'vegan', 'halal', 'meat'].includes(currentFilters.dietary) ? currentFilters.dietary : '';

            fetch('api/meal-kits/filter.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        category: currentFilters.category,
                        dietary: validDietary,
                        calories: currentFilters.calories,
                        price: currentFilters.price,
                        sort: currentFilters.sort
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Store all filtered items for pagination
                        var tempContainer = $('<div>').html(data.html);
                        filteredItems = tempContainer.children().get();
                        
                        // Reset displayed count
                        currentlyDisplayed = 0;
                        
                        // Clear the grid for new items
                        $('#mealKitsGrid').empty();
                        
                        // Display first batch of items
                        var initialCount = Math.min(itemsPerPage, filteredItems.length);
                        for (var i = 0; i < initialCount; i++) {
                            $('#mealKitsGrid').append(filteredItems[i]);
                        }
                        currentlyDisplayed = initialCount;
                        
                        // Update the load more button
                        updateLoadMoreButton();
                        
                        // Re-initialize components for newly added items
                        initializeComponents();
                    } else {
                        // Show error
                        grid.innerHTML = '<div class="col-12 text-center py-5"><div class="alert alert-danger">Failed to load meal kits. Please try again.</div></div>';
                        console.error('Error fetching filtered meal kits:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    grid.innerHTML = '<div class="col-12 text-center py-5"><div class="alert alert-danger">An error occurred while loading meal kits. Please try again.</div></div>';
                });
        }
        
        // Re-initialize components after filtering
        function initializeComponents() {
            // Re-initialize tooltips, popovers, or other Bootstrap components if needed
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Make sure favorite buttons are visible and properly initialized
            $('.favorite-btn').css('display', 'flex');
            
            // Re-initialize favorite buttons
            initializeFavoriteButtons();
            
            // Re-initialize load more button
            initializeLoadMore();
        }
        
        // Update cart count from localStorage as fallback
        function updateCartCountFromStorage() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const count = cart.reduce((total, item) => total + item.quantity, 0);
            updateCartBadge(count);
        }
        
        // Update the cart badge in the navbar
        function updateCartBadge(count) {
            const badge = document.getElementById('cartCount');
            if (badge) {
                badge.textContent = count;
                // Always show the badge, even when count is zero
                badge.style.display = 'inline-block';
            }
        }
    </script>

</body>

</html>