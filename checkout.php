<?php
session_start();
require_once 'config/connection.php';

// Fetch payment methods from payment_settings table
$payment_methods = [];
$payment_stmt = $mysqli->prepare("SELECT * FROM payment_settings");
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
while ($row = $payment_result->fetch_assoc()) {
    $payment_methods[] = $row;
}

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
            'quantity' => $ingredient['quantity']
        ];
    }
    $item['ingredients'] = $ingredients;
    $cart_items[] = $item;
    $total_amount += $item['total_price'];
}

// Calculate delivery fee and total
$delivery_fee = 5.00;
$order_total = $total_amount + $delivery_fee;

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $contact_number = trim($_POST['contact_number']);
    if (!preg_match('/^\+?[0-9]{7,20}$/', $contact_number)) {
        $error_message = 'Please enter a valid contact number (numbers only, may start with + for country code).';
    }
    $delivery_address = filter_var($_POST['delivery_address'], FILTER_SANITIZE_STRING);
    $delivery_notes = filter_var($_POST['delivery_notes'] ?? '', FILTER_SANITIZE_STRING);
    $payment_method = filter_var($_POST['payment_method'], FILTER_SANITIZE_STRING);
    
    // Add file upload handling for transfer slip
    $transfer_slip_path = null;
    if ($payment_method !== 'Cash on Delivery') {
        if (isset($_FILES['transfer_slip']) && $_FILES['transfer_slip']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            if (in_array($_FILES['transfer_slip']['type'], $allowed_types)) {
                $ext = pathinfo($_FILES['transfer_slip']['name'], PATHINFO_EXTENSION);
                $filename = 'slip_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $destination = 'uploads/slips/' . $filename;
                if (!is_dir('uploads/slips')) {
                    mkdir('uploads/slips', 0777, true);
                }
                if (move_uploaded_file($_FILES['transfer_slip']['tmp_name'], $destination)) {
                    $transfer_slip_path = $destination;
                } else {
                    $error_message = 'Failed to upload transfer slip.';
                }
            } else {
                $error_message = 'Invalid file type for transfer slip.';
            }
        } else {
            $error_message = 'Transfer slip is required for this payment method.';
        }
    }
    
    if (empty($delivery_address) || empty($contact_number) || empty($payment_method)) {
        $error_message = 'Please fill in all required fields';
    } else if (empty($cart_items)) {
        $error_message = 'Your cart is empty';
    } else {
        try {
            // Start transaction
            $mysqli->begin_transaction();
            
            // Debug log for bind params (console.log style)
            echo "<script>console.log('Order params: " . addslashes(json_encode([$user_id, $delivery_address, $contact_number, $delivery_notes, $payment_method, $transfer_slip_path, $delivery_fee])) . "');</script>";

            // Create order (summary only, no meal_kit_id, quantity, total_price)
            $stmt = $mysqli->prepare("
                INSERT INTO orders (user_id, delivery_address, contact_number, delivery_notes, payment_method, transfer_slip, delivery_fee)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                echo "<script>console.log('Prepare failed: " . addslashes($mysqli->error) . "');</script>";
            }
            $stmt->bind_param("isssssd", $user_id, $delivery_address, $contact_number, $delivery_notes, $payment_method, $transfer_slip_path, $delivery_fee);
            if (!$stmt->execute()) {
                echo "<script>console.log('Execute failed: " . addslashes($stmt->error) . "');</script>";
            }
            $order_id = $mysqli->insert_id;
            
            // Add order items
            $stmt = $mysqli->prepare("
                INSERT INTO order_items (order_id, meal_kit_id, quantity, price_per_unit, customization_notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                echo "<script>console.log('Order items prepare failed: " . addslashes($mysqli->error) . "');</script>";
            }
            foreach ($cart_items as $item) {
                $stmt->bind_param("iiids", $order_id, $item['meal_kit_id'], $item['quantity'], $item['single_meal_price'], $item['customization_notes']);
                if (!$stmt->execute()) {
                    echo "<script>console.log('Order items execute failed: " . addslashes($stmt->error) . "');</script>";
                }
                $order_item_id = $mysqli->insert_id;
                
                // Add order item ingredients
                $ing_stmt = $mysqli->prepare("
                    SELECT * FROM cart_item_ingredients 
                    WHERE cart_item_id = ?
                ");
                if (!$ing_stmt) {
                    echo "<script>console.log('Cart item ingredients select prepare failed: " . addslashes($mysqli->error) . "');</script>";
                }
                $ing_stmt->bind_param("i", $item['cart_item_id']);
                $ing_stmt->execute();
                $ingredients_result = $ing_stmt->get_result();
                
                // Create order_item_ingredients table if it doesn't exist
                $mysqli->query("
                    CREATE TABLE IF NOT EXISTS order_item_ingredients (
                        order_item_id INT NOT NULL,
                        ingredient_id INT NOT NULL,
                        custom_grams DECIMAL(10,2) NOT NULL,
                        price DECIMAL(10,2) NOT NULL,
                        PRIMARY KEY (order_item_id, ingredient_id),
                        FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id) ON DELETE CASCADE,
                        FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE CASCADE
                    )
                ");
                
                // Insert order item ingredients
                $ing_insert_stmt = $mysqli->prepare("
                    INSERT INTO order_item_ingredients (order_item_id, ingredient_id, custom_grams)
                    VALUES (?, ?, ?)
                ");
                if (!$ing_insert_stmt) {
                    echo "<script>console.log('Order item ingredients insert prepare failed: " . addslashes($mysqli->error) . "');</script>";
                }
                
                while ($ingredient = $ingredients_result->fetch_assoc()) {
                    $ing_insert_stmt->bind_param("iid", $order_item_id, $ingredient['ingredient_id'], $ingredient['quantity']);
                    if (!$ing_insert_stmt->execute()) {
                        echo "<script>console.log('Order item ingredients execute failed: " . addslashes($ing_insert_stmt->error) . "');</script>";
                    }
                }
            }
            
            // Clear cart and cart_item_ingredients for this user after successful order
            $stmt = $mysqli->prepare("DELETE FROM cart_item_ingredients WHERE cart_item_id IN (SELECT cart_item_id FROM cart_items WHERE user_id = ?)");
            if (!$stmt) {
                echo "<script>console.log('Cart item ingredients delete prepare failed: " . addslashes($mysqli->error) . "');</script>";
            }
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                echo "<script>console.log('Cart item ingredients delete execute failed: " . addslashes($stmt->error) . "');</script>";
            }
            $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE user_id = ?");
            if (!$stmt) {
                echo "<script>console.log('Cart items delete prepare failed: " . addslashes($mysqli->error) . "');</script>";
            }
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                echo "<script>console.log('Cart items delete execute failed: " . addslashes($stmt->error) . "');</script>";
            }
            
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
                        <form method="post" action="checkout.php" class="needs-validation" novalidate enctype="multipart/form-data" id="checkoutForm">
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
                                    Please enter a valid contact number (numbers only, may start with + for country code).
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="delivery_notes" class="form-label">Delivery Notes</label>
                                <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="2" placeholder="Any special instructions for delivery..."></textarea>
                            </div>

                            <h5 class="mt-4 mb-3">Payment Method</h5>
                            <div id="slipErrorMsg" class="alert alert-danger d-none mt-2"></div>
                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cashOnDelivery" value="Cash on Delivery" checked>
                                    <label class="form-check-label fw-semibold" for="cashOnDelivery">
                                        <i class="bi bi-cash-coin me-1"></i> Cash on Delivery
                                    </label>
                                </div>
                                <?php foreach ($payment_methods as $method): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="payment_method" id="pm_<?php echo htmlspecialchars($method['payment_method']); ?>" value="<?php echo htmlspecialchars($method['payment_method']); ?>">
                                        <label class="form-check-label fw-semibold" for="pm_<?php echo htmlspecialchars($method['payment_method']); ?>">
                                            <i class="bi bi-qr-code me-1"></i> <?php echo htmlspecialchars($method['payment_method']); ?>
                                        </label>
                                        <?php if (!empty($method['qr_code']) || !empty($method['account_phone'])): ?>
                                            <div class="payment-info card shadow-sm mt-2 ms-4 p-3 border-primary border-2" id="info_<?php echo htmlspecialchars($method['payment_method']); ?>" style="display:none; max-width:360px;">
                                                <?php if (!empty($method['qr_code'])): ?>
                                                    <div class="text-center mb-2">
                                                        <img src="<?php echo htmlspecialchars($method['qr_code']); ?>" alt="<?php echo htmlspecialchars($method['payment_method']); ?> QR" class="img-fluid rounded border" style="max-width:160px;">
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($method['account_phone'])): ?>
                                                    <div class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i> Phone: <span class="fw-semibold"><?php echo htmlspecialchars($method['account_phone']); ?></span></div>
                                                <?php endif; ?>
                                                <div class="mb-2">
                                                    <label for="transfer_slip_<?php echo htmlspecialchars($method['payment_method']); ?>" class="form-label">Upload Transfer Slip <span class="text-danger">*</span></label>
                                                    <input class="form-control form-control-sm" type="file" name="transfer_slip" id="transfer_slip_<?php echo htmlspecialchars($method['payment_method']); ?>" accept="image/*" required style="max-width: 220px;">
                                                    <div class="form-text">Accepted: JPG, PNG, JPEG</div>
                                                    <div id="slip_preview_<?php echo htmlspecialchars($method['payment_method']); ?>" class="mt-2"></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="button" class="btn btn-primary btn-lg" id="showConfirmModalBtn">Place Order</button>
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
                        <?php foreach ($cart_items as $item): ?>
                            <div class="mb-3 border-bottom pb-2">
                                <div class="fw-bold"><?php echo htmlspecialchars($item['meal_kit_name']); ?> x <?php echo $item['quantity']; ?></div>
                                <ul class="mb-1 ps-3">
                                    <?php foreach ($item['ingredients'] as $ingredient): ?>
                                        <li>
                                            <?php echo htmlspecialchars($ingredient['name']); ?>: <?php echo htmlspecialchars($ingredient['quantity']); ?>g
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="text-muted small">Subtotal: $<?php echo number_format($item['total_price'], 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span>Delivery Fee</span>
                            <span>$<?php echo number_format($delivery_fee, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 fw-bold">
                            <span>Total</span>
                            <span>$<?php echo number_format($order_total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-labelledby="confirmOrderModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmOrderModalLabel"><i class="bi bi-check-circle me-2 text-success"></i>Confirm Your Order</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to place this order?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="confirmOrderBtn" class="btn btn-primary">Yes, Place Order</button>
          </div>
        </div>
      </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/toast-notifications.php'; ?>

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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radios = document.querySelectorAll('input[name="payment_method"]');
            const paymentInfos = document.querySelectorAll('.payment-info');
            const slipInputs = document.querySelectorAll('input[type="file"][name="transfer_slip"]');
            const slipErrorMsg = document.getElementById('slipErrorMsg');
            const showConfirmModalBtn = document.getElementById('showConfirmModalBtn');
            const confirmOrderBtn = document.getElementById('confirmOrderBtn');
            const checkoutForm = document.getElementById('checkoutForm');

            function hideAllInfos() {
                paymentInfos.forEach(function(info) {
                    info.style.display = 'none';
                    // Disable slip input when hidden
                    const slip = info.querySelector('input[type="file"][name="transfer_slip"]');
                    if (slip) slip.required = false;
                });
            }

            radios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    hideAllInfos();
                    if (this.checked && this.id.startsWith('pm_')) {
                        const infoDiv = document.getElementById('info_' + this.value);
                        if (infoDiv) {
                            infoDiv.style.display = 'block';
                            // Enable slip input when visible
                            const slip = infoDiv.querySelector('input[type="file"][name="transfer_slip"]');
                            if (slip) slip.required = true;
                        }
                    }
                });
            });

            // Show info if already selected (on reload)
            const checked = document.querySelector('input[name="payment_method"]:checked');
            if (checked && checked.id.startsWith('pm_')) {
                const infoDiv = document.getElementById('info_' + checked.value);
                if (infoDiv) {
                    infoDiv.style.display = 'block';
                    const slip = infoDiv.querySelector('input[type="file"][name="transfer_slip"]');
                    if (slip) slip.required = true;
                }
            }

            // Slip preview logic and error messages
            slipInputs.forEach(function(input) {
                input.addEventListener('change', function(event) {
                    const previewDiv = document.getElementById('slip_preview_' + input.id.replace('transfer_slip_',''));
                    if (previewDiv) {
                        previewDiv.innerHTML = '';
                        const file = event.target.files[0];
                        if (file) {
                            if (!file.type.startsWith('image/')) {
                                slipErrorMsg.textContent = 'Only image files are allowed for transfer slip.';
                                slipErrorMsg.classList.remove('d-none');
                                input.value = '';
                                return;
                            }
                            if (file.size > 5 * 1024 * 1024) { // 5MB
                                slipErrorMsg.textContent = 'File size must be less than 5MB.';
                                slipErrorMsg.classList.remove('d-none');
                                input.value = '';
                                return;
                            }
                            slipErrorMsg.classList.add('d-none');
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewDiv.innerHTML = '<img src="' + e.target.result + '" class="img-thumbnail shadow-sm" style="max-width:140px;max-height:140px;">';
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            });

            // Confirmation modal logic
            showConfirmModalBtn.addEventListener('click', function(e) {
                // Validate form before showing modal
                slipErrorMsg.classList.add('d-none');
                if (!checkoutForm.checkValidity()) {
                    checkoutForm.classList.add('was-validated');
                    return;
                }
                // If slip is required, check again for file
                const selectedRadio = document.querySelector('input[name="payment_method"]:checked');
                if (selectedRadio && selectedRadio.id.startsWith('pm_')) {
                    const slipInput = document.getElementById('transfer_slip_' + selectedRadio.value);
                    if (!slipInput || !slipInput.files.length) {
                        slipErrorMsg.textContent = 'Please upload a transfer slip image.';
                        slipErrorMsg.classList.remove('d-none');
                        return;
                    }
                }
                var confirmModal = new bootstrap.Modal(document.getElementById('confirmOrderModal'));
                confirmModal.show();
            });

            confirmOrderBtn.addEventListener('click', function(e) {
                // Actually submit the form
                checkoutForm.submit();
            });
        });
    </script>

</body>

</html>
<?php
// Close the database connection
$mysqli->close();
?>
