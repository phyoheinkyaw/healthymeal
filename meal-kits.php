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

// Fetch categories for filter
$categories = $mysqli->query("SELECT * FROM categories ORDER BY name");

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
                                    <option value="under500">Under 500 cal</option>
                                    <option value="500-800">500-800 cal</option>
                                    <option value="over800">Over 800 cal</option>
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
            <?php while ($mealKit = $mealKits->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4 meal-kit-card" data-category="<?php echo $mealKit['category_id']; ?>"
                data-calories="<?php echo $mealKit['base_calories']; ?>">
                <div class="card h-100">
                    <?php $img_url = get_meal_kit_image_url($mealKit['image_url'], $mealKit['name']); ?>
                    <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top" alt="Meal Kit Image">

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
                                $<?php echo number_format($mealKit['preparation_price']+$mealKit['ingredients_price'], 2); ?>
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
            <?php endwhile; ?>
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

    <!-- Bootstrap JS -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->

    <script>

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

    function updateNutritionalValues() {
        // Get base values from the form if available
        const baseCaloriesEl = document.getElementById('baseCalories');
        const basePriceEl = document.getElementById('basePrice');

        // Initialize totals with base values if available
        let totalCalories = baseCaloriesEl ? parseFloat(baseCaloriesEl.textContent) || 0 : 0;
        let totalProtein = 0;
        let totalCarbs = 0;
        let totalFat = 0;
        let ingredientsPrice = 0;
        const preparationPrice = basePriceEl ? parseFloat(basePriceEl.textContent) || 0 : 0;

        document.querySelectorAll('.ingredient-row').forEach(row => {
            const quantityInput = row.querySelector('.ingredient-quantity');
            if (!quantityInput) return;

            const quantity = parseFloat(quantityInput.value) || 0;
            const caloriesPer100g = parseFloat(row.dataset.calories) || 0;
            const proteinPer100g = parseFloat(row.dataset.protein) || 0;
            const carbsPer100g = parseFloat(row.dataset.carbs) || 0;
            const fatPer100g = parseFloat(row.dataset.fat) || 0;
            const pricePer100g = parseFloat(row.dataset.price) || 0;

            const calories = (caloriesPer100g * quantity) / 100;
            const protein = (proteinPer100g * quantity) / 100;
            const carbs = (carbsPer100g * quantity) / 100;
            const fat = (fatPer100g * quantity) / 100;
            const price = (pricePer100g * quantity) / 100;

            totalCalories += calories;
            totalProtein += protein;
            totalCarbs += carbs;
            totalFat += fat;
            ingredientsPrice += price;

            // Update individual row values if cells exist
            const caloriesCell = row.querySelector('.calories-cell');
            const proteinCell = row.querySelector('.protein-cell');
            const carbsCell = row.querySelector('.carbs-cell');
            const fatCell = row.querySelector('.fat-cell');
            const priceCell = row.querySelector('.price-cell');

            if (caloriesCell) caloriesCell.textContent = Math.round(calories) + ' cal';
            if (proteinCell) proteinCell.textContent = protein.toFixed(1) + 'g';
            if (carbsCell) carbsCell.textContent = carbs.toFixed(1) + 'g';
            if (fatCell) fatCell.textContent = fat.toFixed(1) + 'g';
            if (priceCell) priceCell.textContent = '$' + price.toFixed(2);
        });

        // Update totals
        const totalCaloriesEl = document.getElementById('totalCalories');
        const totalProteinEl = document.getElementById('totalProtein');
        const totalCarbsEl = document.getElementById('totalCarbs');
        const totalFatEl = document.getElementById('totalFat');
        const ingredientsPriceEl = document.getElementById('ingredientsPrice');
        const totalPriceEl = document.getElementById('totalPrice');

        // Get meal quantity
        const mealQuantity = parseInt(document.getElementById('meal_quantity')?.value) || 1;

        // Calculate total price (preparation price + ingredients price) * quantity
        const totalPrice = (preparationPrice + ingredientsPrice) * mealQuantity;

        if (totalCaloriesEl) totalCaloriesEl.textContent = Math.round(totalCalories);
        if (totalProteinEl) totalProteinEl.textContent = totalProtein.toFixed(1);
        if (totalCarbsEl) totalCarbsEl.textContent = totalCarbs.toFixed(1);
        if (totalFatEl) totalFatEl.textContent = totalFat.toFixed(1);
        if (ingredientsPriceEl) ingredientsPriceEl.textContent = ingredientsPrice.toFixed(2);
        if (totalPriceEl) totalPriceEl.textContent = totalPrice.toFixed(2);
    }

    // For backward compatibility
    function updateTotalCalories() {
        updateNutritionalValues();
    }

    function applyFilters() {
        const category = document.getElementById('categoryFilter').value;
        const dietary = document.getElementById('dietaryFilter').value;
        const calories = document.getElementById('calorieFilter').value;
        const sort = document.getElementById('sortBy').value;

        fetch('api/meal-kits/filter.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    category: category,
                    dietary: dietary,
                    calories: calories,
                    sort: sort
                })
            })
            .then(response => response.json())
            .then(data => {
                const grid = document.getElementById('mealKitsGrid');
                grid.innerHTML = data.html;

                // Reinitialize dropdowns after content update
                var newDropdownTriggerList = [].slice.call(document.querySelectorAll(
                    '[data-bs-toggle="dropdown"]'));
                var newDropdownList = newDropdownTriggerList.map(function(dropdownTriggerEl) {
                    return new bootstrap.Dropdown(dropdownTriggerEl);
                });
            })
            .catch(error => console.error('Error:', error));
    }

    // Add event listeners to filters
    document.getElementById('categoryFilter').addEventListener('change', applyFilters);
    document.getElementById('dietaryFilter').addEventListener('change', applyFilters);
    document.getElementById('calorieFilter').addEventListener('change', applyFilters);
    document.getElementById('sortBy').addEventListener('change', applyFilters);
    </script>

</body>

</html>