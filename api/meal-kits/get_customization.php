<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_GET['meal_kit_id'])) {
    echo json_encode(['success' => false, 'message' => 'Meal kit ID is required']);
    exit();
}

$meal_kit_id = filter_var($_GET['meal_kit_id'], FILTER_VALIDATE_INT);

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
    error_log("Error preparing meal kit query: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    exit();
}

$stmt->bind_param("i", $meal_kit_id);
$stmt->execute();
$meal_kit = $stmt->get_result()->fetch_assoc();

if (!$meal_kit) {
    error_log("Meal kit not found for ID: " . $meal_kit_id);
    echo json_encode(['success' => false, 'message' => 'Meal kit not found']);
    exit();
}

// Get ingredients with their quantities and nutritional info
$stmt = $mysqli->prepare("
    SELECT i.*, mki.default_quantity,
           (i.calories_per_100g * mki.default_quantity / 100) as ingredient_calories,
           (i.price_per_100g * mki.default_quantity / 100) as ingredient_price
    FROM meal_kit_ingredients mki
    JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    WHERE mki.meal_kit_id = ?
");
if (!$stmt) {
    error_log("Error preparing ingredients query: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    exit();
}

$stmt->bind_param("i", $meal_kit_id);
$stmt->execute();
$ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($ingredients)) {
    error_log("No ingredients found for meal kit ID: " . $meal_kit_id);
    echo json_encode(['success' => false, 'message' => 'No ingredients found for this meal kit']);
    exit();
}

// Calculate initial totals (only from ingredients)
$total_calories = 0;
$total_protein = 0;
$total_carbs = 0;
$total_fat = 0;
$ingredients_price = 0;
$preparation_price = $meal_kit['preparation_price'];

foreach ($ingredients as $ingredient) {
    $total_calories += ($ingredient['calories_per_100g'] * $ingredient['default_quantity']) / 100;
    $total_protein += ($ingredient['protein_per_100g'] * $ingredient['default_quantity']) / 100;
    $total_carbs += ($ingredient['carbs_per_100g'] * $ingredient['default_quantity']) / 100;
    $total_fat += ($ingredient['fat_per_100g'] * $ingredient['default_quantity']) / 100;
    $ingredients_price += ($ingredient['price_per_100g'] * $ingredient['default_quantity']) / 100;
}

// Calculate total price (preparation price + ingredients price)
$total_price = $preparation_price + $ingredients_price;

// We'll use an external JavaScript file instead of embedding JS code here

// Generate HTML for the modal
$html = '
<form id="customizeForm" class="needs-validation" novalidate>
    <input type="hidden" name="meal_kit_id" value="' . $meal_kit_id . '">
    
    <div class="row mb-4">
        <div class="col-md-9">
            <h4>' . htmlspecialchars($meal_kit['name']) . '</h4>
            <p class="text-muted">' . htmlspecialchars($meal_kit['description']) . '</p>
        </div>
        <div class="col-md-3">
            <label for="meal_quantity" class="form-label">Meal Quantity</label>
            <div class="input-group">
                <button type="button" class="btn btn-outline-secondary" onclick="decrementQuantity()">-</button>
                <input type="number" class="form-control text-center" id="meal_quantity" name="meal_quantity" value="1" min="1" max="10">
                <button type="button" class="btn btn-outline-secondary" onclick="incrementQuantity()">+</button>
            </div>
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Quantity (g)</th>
                    <th>Calories</th>
                    <th>Protein</th>
                    <th>Carbs</th>
                    <th>Fat</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>';

foreach ($ingredients as $ingredient) {
    $html .= '
        <tr class="ingredient-row" 
            data-ingredient-id="' . $ingredient['ingredient_id'] . '"
            data-calories="' . $ingredient['calories_per_100g'] . '"
            data-protein="' . $ingredient['protein_per_100g'] . '"
            data-carbs="' . $ingredient['carbs_per_100g'] . '"
            data-fat="' . $ingredient['fat_per_100g'] . '"
            data-price="' . $ingredient['price_per_100g'] . '">
            <td>
                ' . htmlspecialchars($ingredient['name']) . '
                <small class="text-muted d-block">' . round($ingredient['calories_per_100g']) . ' cal per 100g</small>
            </td>
            <td>
                <input type="number" 
                       class="form-control form-control-sm ingredient-quantity" 
                       name="ingredients[' . $ingredient['ingredient_id'] . ']" 
                       value="' . $ingredient['default_quantity'] . '"
                       min="0"
                       max="1000"
                       step="10"
                       style="width: 100px">
            </td>
            <td class="text-end calories-cell">' . round($ingredient['ingredient_calories']) . ' cal</td>
            <td class="text-end protein-cell">' . number_format($ingredient['protein_per_100g'] * $ingredient['default_quantity'] / 100, 1) . 'g</td>
            <td class="text-end carbs-cell">' . number_format($ingredient['carbs_per_100g'] * $ingredient['default_quantity'] / 100, 1) . 'g</td>
            <td class="text-end fat-cell">' . number_format($ingredient['fat_per_100g'] * $ingredient['default_quantity'] / 100, 1) . 'g</td>
            <td class="text-end price-cell">$' . number_format($ingredient['ingredient_price'], 2) . '</td>
        </tr>';
}

$html .= '
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Ingredients Total</strong></td>
                    <td></td>
                    <td class="text-end"><strong><span id="totalCalories">' . round($total_calories) . '</span> cal</strong></td>
                    <td class="text-end"><strong><span id="totalProtein">' . number_format($total_protein, 1) . '</span>g</strong></td>
                    <td class="text-end"><strong><span id="totalCarbs">' . number_format($total_carbs, 1) . '</span>g</strong></td>
                    <td class="text-end"><strong><span id="totalFat">' . number_format($total_fat, 1) . '</span>g</strong></td>
                    <td class="text-end"><strong>$<span id="ingredientsPrice">' . number_format($ingredients_price, 2) . '</span></strong></td>
                </tr>
                <tr>
                    <td><strong>Preparation Price</strong></td>
                    <td colspan="5"></td>
                    <td class="text-end"><strong>$<span id="basePrice">' . number_format($preparation_price, 2) . '</span></strong></td>
                </tr>
                <tr>
                    <td><strong>Single Meal Price</strong></td>
                    <td colspan="5"></td>
                    <td class="text-end"><strong>$<span id="singleMealPrice">' . number_format($total_price, 2) . '</span></strong></td>
                </tr>
                <tr class="table-info">
                    <td><strong>Total Price</strong></td>
                    <td colspan="5"></td>
                    <td class="text-end"><strong>$<span id="totalPrice">' . number_format($total_price, 2) . '</span></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <label for="customization_notes" class="form-label">Special Instructions</label>
            <textarea class="form-control" id="customization_notes" name="customization_notes" rows="2" placeholder="Any special instructions or requests for this meal kit..."></textarea>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> 
                Adjust ingredient quantities to match your preferences and dietary needs.
                The preparation price covers cooking, packaging, and handling costs.
                Total price is the sum of preparation price and ingredients price.
            </div>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="standardAddToCart(' . $meal_kit_id . ', document.getElementById(\'meal_quantity\').value)">Add to Cart</button>
        </div>
    </div>
</form>

<script type="text/javascript">
// Initialize listeners after content is loaded
initializeQuantityListeners();
</script>';

// Return the HTML content
echo json_encode([
    'success' => true,
    'html' => $html
]);

$mysqli->close();