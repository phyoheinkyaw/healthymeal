<?php
session_start();
require_once 'config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Fetch user information
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch cart items
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
    $cart_items[] = $item;
    $total_amount += $item['total_price'];
}

// Calculate delivery fee and total
$delivery_fee = 5.00;
$order_total = $total_amount + $delivery_fee;

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $delivery_address = filter_var($_POST['delivery_address'], FILTER_SANITIZE_STRING);
    $contact_number = filter_var($_POST['contact_number'], FILTER_SANITIZE_STRING);
    $delivery_notes = filter_var($_POST['delivery_notes'] ?? '', FILTER_SANITIZE_STRING);
    $payment_method = filter_var($_POST['payment_method'], FILTER_SANITIZE_STRING);
    
    if (empty($delivery_address) || empty($contact_number) || empty($payment_method)) {
        $error_message = 'Please fill in all required fields';
    } else if (empty($cart_items)) {
        $error_message = 'Your cart is empty';
    } else {
        try {
            // Start transaction
            $mysqli->begin_transaction();
            
            // Create order
            $stmt = $mysqli->prepare("
                INSERT INTO orders (user_id, status_id, created_at, delivery_address, contact_number, delivery_notes, payment_method, delivery_fee)
                VALUES (?, 1, NOW(), ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssd", $user_id, $delivery_address, $contact_number, $delivery_notes, $payment_method, $delivery_fee);
            $stmt->execute();
            
            $order_id = $mysqli->insert_id;
            
            // Add order items
            $stmt = $mysqli->prepare("
                INSERT INTO order_items (order_id, meal_kit_id, quantity, price_per_unit, customization_notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $stmt->bind_param("iiids", $order_id, $item['meal_kit_id'], $item['quantity'], $item['single_meal_price'], $item['customization_notes']);
                $stmt->execute();
                
                // Get the order item ID
                $order_item_id = $mysqli->insert_id;
                
                // Add order item ingredients
                $ing_stmt = $mysqli->prepare("
                    SELECT * FROM cart_item_ingredients 
                    WHERE cart_item_id = ?
                ");
                $ing_stmt->bind_param("i", $item['cart_item_id']);
                $ing_stmt->execute();
                $ingredients_result = $ing_stmt->get_result();
                
                // Create order_item_ingredients table if it doesn't exist
                $mysqli->query("
                    CREATE TABLE IF NOT EXISTS order_item_ingredients (
                        order_item_id INT NOT NULL,
                        ingredient_id INT NOT NULL,
                        quantity DECIMAL(10,2) NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        PRIMARY KEY (order_item_id, ingredient_id),
                        FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE,
                        FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE CASCADE
                    )
                ");
                
                // Insert order item ingredients
                $ing_insert_stmt = $mysqli->prepare("
                    INSERT INTO order_item_ingredients (order_item_id, ingredient_id, quantity, price)
                    VALUES (?, ?, ?, ?)
                ");
                
                while ($ingredient = $ingredients_result->fetch_assoc()) {
                    $ing_insert_stmt->bind_param("iidd", $order_item_id, $ingredient['ingredient_id'], $ingredient['quantity'], $ingredient['price']);
                    $ing_insert_stmt->execute();
                }
            }
            
            // Clear cart
            $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Update session cart count
            $_SESSION['cart_count'] = 0;
            
            // Commit transaction
            $mysqli->commit();
            
            // Set a flag in session to update cart count on next page load
            $_SESSION['reset_cart_count'] = true;
            
            // Redirect to orders page with success message
            header("Location: orders.php?success=1&message=" . urlencode('Your order has been placed successfully!'));
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $mysqli->rollback();
            $error_message = 'An error occurred while processing your order. Please try again.';
            error_log("Checkout error: " . $e->getMessage());
            
            // Show error toast
            echo '<script>
                const errorToast = document.getElementById("errorToast");
                if (errorToast) {
                    const errorMsg = document.getElementById("errorToastMessage");
                    if (errorMsg) {
                        errorMsg.textContent = "' . addslashes($error_message) . '";
                    }
                    const bsToast = new bootstrap.Toast(errorToast);
                    bsToast.show();
                }
            </script>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Healthy Meal Kit</title>
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
        <h1 class="mb-4">Checkout</h1>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Your cart is empty.
            <a href="meal-kits.php" class="alert-link">Browse meal kits</a> to add items.
        </div>
        <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Checkout Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Delivery Information</h5>
                        <form method="post" action="checkout.php" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" required></textarea>
                                <div class="invalid-feedback">
                                    Please enter your delivery address.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="contact_number" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                                <div class="invalid-feedback">
                                    Please enter your contact number.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_notes" class="form-label">Delivery Notes</label>
                                <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="2" placeholder="Any special instructions for delivery..."></textarea>
                            </div>

                            <h5 class="mt-4 mb-3">Payment Method</h5>
                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cashOnDelivery" value="Cash on Delivery" checked>
                                    <label class="form-check-label" for="cashOnDelivery">
                                        Cash on Delivery
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="creditCard" value="Credit Card">
                                    <label class="form-check-label" for="creditCard">
                                        Credit Card
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="PayPal">
                                    <label class="form-check-label" for="paypal">
                                        PayPal
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%); color: white;">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Delivery Fee:</span>
                            <span>$<?php echo number_format($delivery_fee, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($order_total, 2); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($cart_items as $item): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['meal_kit_name']); ?></h6>
                                        <small class="text-muted">Quantity: <?php echo $item['quantity']; ?></small>
                                    </div>
                                    <span>$<?php echo number_format($item['total_price'], 2); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/toast-notifications.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    // Form validation
    (function() {
        'use strict';
        
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();
    </script>

</body>

</html>
<?php
// Close the database connection
$mysqli->close();
?>
