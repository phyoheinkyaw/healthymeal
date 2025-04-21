<?php
session_start();
require_once 'config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Helper function to get meal kit image URL
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0];
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

// Fetch cart items from database
$stmt = $mysqli->prepare("
    SELECT ci.*, mk.name as meal_kit_name, mk.image_url, c.name as category_name
    FROM cart_items ci
    JOIN meal_kits mk ON ci.meal_kit_id = mk.meal_kit_id
    LEFT JOIN categories c ON mk.category_id = c.category_id
    WHERE ci.user_id = ?
    ORDER BY ci.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items_result = $stmt->get_result();

$cart_items = [];
$total_amount = 0;

while ($item = $cart_items_result->fetch_assoc()) {
    // Get ingredient details for each cart item
    $ing_stmt = $mysqli->prepare("
        SELECT cii.*, i.name as ingredient_name
        FROM cart_item_ingredients cii
        JOIN ingredients i ON cii.ingredient_id = i.ingredient_id
        WHERE cii.cart_item_id = ?
    ");
    $ing_stmt->bind_param("i", $item['cart_item_id']);
    $ing_stmt->execute();
    $ingredients_result = $ing_stmt->get_result();
    
    $ingredients = [];
    
    while ($ingredient = $ingredients_result->fetch_assoc()) {
        $ingredients[] = [
            'name' => $ingredient['ingredient_name'],
            'quantity' => $ingredient['quantity'],
            'price' => $ingredient['price']
        ];
    }
    
    // Calculate total calories (if needed)
    $cal_stmt = $mysqli->prepare("
        SELECT SUM(i.calories_per_100g * cii.quantity / 100) as total_calories
        FROM cart_item_ingredients cii
        JOIN ingredients i ON cii.ingredient_id = i.ingredient_id
        WHERE cii.cart_item_id = ?
    ");
    $cal_stmt->bind_param("i", $item['cart_item_id']);
    $cal_stmt->execute();
    $cal_result = $cal_stmt->get_result();
    $calories = $cal_result->fetch_assoc();
    $total_calories = $calories['total_calories'] ?? 0;
    
    $cart_items[] = [
        'cart_item_id' => $item['cart_item_id'],
        'meal_kit' => [
            'meal_kit_id' => $item['meal_kit_id'],
            'name' => $item['meal_kit_name'],
            'image_url' => $item['image_url'],
            'category_name' => $item['category_name']
        ],
        'ingredients' => $ingredients,
        'quantity' => $item['quantity'],
        'single_meal_price' => $item['single_meal_price'],
        'total_price' => $item['total_price'],
        'total_calories' => $total_calories,
        'customization_notes' => $item['customization_notes']
    ];
    
    $total_amount += $item['total_price'];
}

// Update session cart count for consistency
$total_items = array_sum(array_column($cart_items_result->fetch_all(MYSQLI_ASSOC), 'quantity'));
$_SESSION['cart_count'] = $total_items;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <h1 class="mb-4">Shopping Cart</h1>

        <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Your cart is empty.
            <a href="meal-kits.php" class="alert-link">Browse meal kits</a> to add items.
        </div>
        <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Cart Items -->
                <div class="card mb-4">
                    <div class="card-body">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-md-3">
                                <?php $img_url = get_meal_kit_image_url($item['meal_kit']['image_url'], $item['meal_kit']['name']); ?>
                                <img src="<?php echo htmlspecialchars($img_url); ?>"
                                    class="img-fluid rounded"
                                    alt="<?php echo htmlspecialchars($item['meal_kit']['name']); ?>">
                            </div>
                            <div class="col-md-9">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($item['meal_kit']['name']); ?></h5>
                                        <p class="text-muted mb-2">
                                            <?php echo htmlspecialchars($item['meal_kit']['category_name']); ?></p>
                                        <p class="mb-2">
                                            <span
                                                class="badge bg-info"><?php echo isset($item['total_calories']) ? round($item['total_calories']) : '0'; ?>
                                                cal</span>
                                        </p>
                                        <?php if (!empty($item['customization_notes'])): ?>
                                        <p class="small mb-2">
                                            <span class="badge bg-secondary"><i class="bi bi-pencil-fill me-1"></i>Special Instructions</span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" 
                                                onclick="editNotes(<?php echo $item['cart_item_id']; ?>, '<?php echo addslashes($item['customization_notes']); ?>')">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <div class="mt-1 p-2 border-start" style="border-width: 3px !important; border-color: var(--primary) !important;">
                                                <?php echo htmlspecialchars($item['customization_notes']); ?>
                                            </div>
                                        </p>
                                        <?php else: ?>
                                        <p class="small mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="editNotes(<?php echo $item['cart_item_id']; ?>, '')">
                                                <i class="bi bi-plus-circle"></i> Add Special Instructions
                                            </button>
                                        </p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['ingredients'])): ?>
                                        <div class="small">
                                            <strong>Customized Ingredients:</strong>
                                            <p class="mb-1 text-muted"><small><i class="bi bi-info-circle"></i>
                                                    Ingredient amounts shown in grams (g)</small></p>
                                            <ul class="mb-0">
                                                <?php foreach ($item['ingredients'] as $ingredient): ?>
                                                <li>
                                                    <?php echo htmlspecialchars($ingredient['name']); ?>:
                                                    <?php echo round($ingredient['quantity']); ?>g
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <h6 class="mb-2">
                                            $<?php echo number_format($item['total_price'], 2); ?>
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="removeCartItem(<?php echo $item['cart_item_id']; ?>)">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mt-2">
                                    <div class="d-flex align-items-center">
                                        <label class="me-2"><strong>Meal Quantity:</strong></label>
                                        <div class="input-group" style="width: 120px;">
                                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                                onclick="updateCartItemQuantity(<?php echo $item['cart_item_id']; ?>, <?php echo max(1, $item['quantity'] - 1); ?>)">-</button>
                                            <input type="number" class="form-control form-control-sm text-center" 
                                                value="<?php echo $item['quantity']; ?>" min="1" max="10" 
                                                id="quantity-<?php echo $item['cart_item_id']; ?>"
                                                onchange="updateCartItemQuantity(<?php echo $item['cart_item_id']; ?>, this.value)">
                                            <button class="btn btn-outline-secondary btn-sm" type="button"
                                                onclick="updateCartItemQuantity(<?php echo $item['cart_item_id']; ?>, <?php echo min(10, $item['quantity'] + 1); ?>)">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            color: white;">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Delivery Fee:</span>
                            <span>$5.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($total_amount + 5, 2); ?></strong>
                        </div>
                        <div class="d-grid">
                            <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/toast-notifications.php'; ?>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notesModalLabel">Edit Special Instructions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Content will be dynamically inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Function to update cart item quantity
    function updateCartItemQuantity(cartItemId, quantity) {
        quantity = parseInt(quantity);
        if (isNaN(quantity) || quantity < 1) {
            quantity = 1;
        }
        
        fetch('api/cart/db_update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update',
                cart_item_id: cartItemId,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update cart count
                updateCartCount(data.total_items);
                
                // Reload page to reflect changes
                window.location.reload();
            } else {
                document.getElementById('errorToastMessage').textContent = data.message || 'Error updating cart';
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('errorToastMessage').textContent = 'Error updating cart. Please try again.';
            const toast = new bootstrap.Toast(document.getElementById('errorToast'));
            toast.show();
        });
    }
    
    // Function to remove cart item
    function removeCartItem(cartItemId) {
        if (confirm('Are you sure you want to remove this item from your cart?')) {
            fetch('api/cart/db_update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'remove',
                    cart_item_id: cartItemId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart count
                    updateCartCount(data.total_items);
                    
                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    document.getElementById('errorToastMessage').textContent = data.message || 'Error removing item';
                    const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                    toast.show();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('errorToastMessage').textContent = 'Error removing item. Please try again.';
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
            });
        }
    }
    
    // Function to update cart count
    function updateCartCount(count) {
        const cartCount = count || 0;
        const cartCountElement = document.getElementById('cartCount');
        if (cartCountElement) {
            cartCountElement.textContent = cartCount;
        }
        localStorage.setItem('cartCount', cartCount);
    }
    
    // Function to edit notes
    function editNotes(cartItemId, notes) {
        const modal = document.getElementById('notesModal');
        const modalBody = modal.querySelector('.modal-body');
        const modalTitle = modal.querySelector('.modal-title');
        
        modalTitle.textContent = 'Edit Special Instructions';
        modalBody.innerHTML = `
            <textarea class="form-control" id="notes" rows="5">${notes}</textarea>
            <button type="button" class="btn btn-primary mt-3" onclick="saveNotes(${cartItemId})">Save Changes</button>
        `;
        
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
    }
    
    // Function to save notes
    function saveNotes(cartItemId) {
        const notes = document.getElementById('notes').value.trim();
        
        fetch('api/cart/db_update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_notes',
                cart_item_id: cartItemId,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to reflect changes
                window.location.reload();
            } else {
                document.getElementById('errorToastMessage').textContent = data.message || 'Error updating notes';
                const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                toast.show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('errorToastMessage').textContent = 'Error updating notes. Please try again.';
            const toast = new bootstrap.Toast(document.getElementById('errorToast'));
            toast.show();
        });
    }
    </script>

</body>

</html>
<?php
// Close the database connection
$mysqli->close();
?>
