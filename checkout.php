<?php
session_start();
require_once 'config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch cart items first to check if cart is empty
$cart_check = $mysqli->prepare("SELECT COUNT(*) as count FROM cart_items WHERE user_id = ?");
$cart_check->bind_param("i", $user_id);
$cart_check->execute();
$cart_count_result = $cart_check->get_result();
$cart_count = $cart_count_result->fetch_assoc()['count'];

// Redirect to cart page if cart is empty
if ($cart_count == 0) {
    $_SESSION['message'] = [
        'type' => 'warning',
        'text' => 'Your cart is empty. Please add items before proceeding to checkout.'
    ];
    header("Location: cart.php");
    exit();
}

// Fetch payment methods from payment_settings table
$payment_methods = [];
$cash_on_delivery_icon = 'bi bi-cash-coin'; // Default icon
$cash_on_delivery_id = null;
$cash_on_delivery_active = false; // Track if Cash on Delivery is active
$payment_stmt = $mysqli->prepare("SELECT * FROM payment_settings WHERE is_active = 1");
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
while ($row = $payment_result->fetch_assoc()) {
    $payment_methods[] = $row;
    // Store the Cash on Delivery icon and ID if found
    if ($row['payment_method'] === 'Cash on Delivery') {
        $cash_on_delivery_icon = $row['icon_class'];
        $cash_on_delivery_id = $row['id'];
        $cash_on_delivery_active = true; // Mark as active
    }
}

// Fetch delivery options
$delivery_options = [];
$delivery_stmt = $mysqli->prepare("
    SELECT delivery_option_id, name, description, fee, 
           TIME_FORMAT(time_slot, '%h:%i %p') as formatted_time,
           time_slot
    FROM delivery_options
    WHERE is_active = 1
    ORDER BY time_slot ASC
");
$delivery_stmt->execute();
$delivery_result = $delivery_stmt->get_result();
while ($row = $delivery_result->fetch_assoc()) {
    $delivery_options[] = $row;
}

$error_message = '';
$success_message = '';

// Fetch user information
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch user's saved addresses
$addresses = [];
$addr_stmt = $mysqli->prepare("
    SELECT * FROM user_addresses 
    WHERE user_id = ? 
    ORDER BY is_default DESC, address_name ASC
");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addr_result = $addr_stmt->get_result();
while ($addr = $addr_result->fetch_assoc()) {
    $addresses[] = $addr;
}

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

// Calculate tax and delivery fee
$tax = round($total_amount * 0.05); // 5% tax - whole number for MMK
// Delivery fee will be selected by the user via delivery options

// Calculate total (will be updated via JS when delivery option is selected)
$order_total = $total_amount + $tax;
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
    <style>
        /* Collapsible Section Styling */
        .section-header {
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 0;
            background: linear-gradient(to right, rgba(var(--bs-light-rgb), 0.5), rgba(var(--bs-light-rgb), 0.2));
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: relative;
            z-index: 1;
        }
        
        .section-header:hover {
            background: linear-gradient(to right, rgba(var(--bs-primary-rgb), 0.05), rgba(var(--bs-primary-rgb), 0.01));
        }
        
        .section-header.active {
            background: linear-gradient(to right, rgba(var(--bs-primary-rgb), 0.1), rgba(var(--bs-primary-rgb), 0.05));
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            border-bottom: 2px solid var(--primary);
            margin-bottom: 0;
        }
        
        .section-header.has-error {
            background: linear-gradient(to right, rgba(var(--bs-danger-rgb), 0.1), rgba(var(--bs-danger-rgb), 0.05));
            border-bottom: 2px solid var(--danger);
        }
        
        .section-header .bi {
            transition: transform 0.4s ease, color 0.2s ease;
            color: var(--secondary);
        }
        
        .section-header:hover .bi {
            color: var(--primary);
        }
        
        .section-header.has-error .bi {
            color: var(--danger);
        }
        
        .section-content {
            overflow: hidden;
            transition: max-height 0.4s ease-in-out, opacity 0.3s ease-in-out, padding 0.2s ease;
            background-color: rgba(var(--bs-light-rgb), 0.3);
            border-radius: 0 0 8px 8px;
            opacity: 1;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 3px rgba(0,0,0,0.03);
            border-left: 1px solid rgba(var(--bs-primary-rgb), 0.1);
            border-right: 1px solid rgba(var(--bs-primary-rgb), 0.1);
            border-bottom: 1px solid rgba(var(--bs-primary-rgb), 0.1);
        }
        
        .section-content.collapsed {
            max-height: 0;
            opacity: 0;
            padding-top: 0;
            padding-bottom: 0;
            margin-bottom: 10px;
            border: none;
        }
        
        /* Pulse effect for chevron on hover */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .section-header:hover .bi {
            animation: pulse 1s ease infinite;
        }
        
        /* Custom styles for section icons */
        .section-icon {
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            padding: 5px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            width: 32px;
            height: 32px;
            color: var(--primary);
        }
        
        .section-header.has-error .section-icon {
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            color: var(--danger);
        }
        
        /* Form section styles */
        .form-section-heading {
            font-weight: 600;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }
        
        /* Form validation styles */
        .form-control.is-invalid {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: var(--danger);
        }
        
        .form-control.is-invalid ~ .invalid-feedback {
            display: block;
        }
        
        /* Alert for validation */
        .validation-alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
            font-size: 0.9rem;
            display: none;
        }
        
        .validation-alert.show {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Add pointer cursor to clickable elements */
        .form-check-label, .btn, .btn-sm, .alert-link, a {
            cursor: pointer;
        }
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <h1 class="mb-4">Checkout</h1>
        
        <div id="alertContainer">
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
        </div>

        <?php if (empty($cart_items)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Your cart is empty.
            <a href="meal-kits.php" class="alert-link">Browse meal kits</a> to add items.
        </div>
        <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Checkout Form -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <!-- Validation alert area -->
                        <div class="validation-alert" id="validationAlert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <span>Please fill in all required fields to complete your order.</span>
                        </div>
                
                        <form method="post" id="checkoutForm" class="needs-validation" novalidate>
                            <!-- Personal Information Section -->
                            <div class="section-header d-flex justify-content-between align-items-center mb-0 active" 
                                 onclick="toggleSection('personalInfoSection', this)">
                                <h5 class="form-section-heading">
                                    <span class="section-icon"><i class="bi bi-person"></i></span>
                                    Personal Information
                                </h5>
                                <i class="bi bi-chevron-up" id="personalInfoSection-icon"></i>
                            </div>
                            <div class="section-content" id="personalInfoSection">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="fullName" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Contact Information Section -->
                            <div class="section-header d-flex justify-content-between align-items-center mb-0 mt-3 active" 
                                 onclick="toggleSection('contactInfoSection', this)">
                                <h5 class="form-section-heading">
                                    <span class="section-icon"><i class="bi bi-telephone"></i></span>
                                    Contact Information
                                </h5>
                                <i class="bi bi-chevron-up" id="contactInfoSection-icon"></i>
                            </div>
                            <div class="section-content" id="contactInfoSection">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="customerPhone" class="form-label">Customer Phone <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                            <input type="tel" class="form-control" id="customerPhone" name="customer_phone" required>
                                            <div class="invalid-feedback">Please enter your phone number.</div>
                                        </div>
                                        <div class="form-text">Your primary contact number</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contactNumber" class="form-label">Alternate Contact <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input type="tel" class="form-control" id="contactNumber" name="contact_number" required>
                                            <div class="invalid-feedback">Please enter an alternate contact number.</div>
                                        </div>
                                        <div class="form-text">Alternative number in case we can't reach you</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delivery Address Section -->
                            <div class="section-header d-flex justify-content-between align-items-center mb-0 mt-3 active" 
                                 onclick="toggleSection('deliveryAddressSection', this)">
                                <h5 class="form-section-heading">
                                    <span class="section-icon"><i class="bi bi-geo-alt"></i></span>
                                    Delivery Address
                                </h5>
                                <i class="bi bi-chevron-up" id="deliveryAddressSection-icon"></i>
                            </div>
                            <div class="section-content" id="deliveryAddressSection">
                                <?php 
                                // Find default address
                                $default_address = null;
                                foreach ($addresses as $addr) {
                                    if ($addr['is_default']) {
                                        $default_address = $addr;
                                        break;
                                    }
                                }
                                ?>

                                <?php if (!empty($addresses)): ?>
                                <div class="mb-4">
                                    <label class="form-label">Saved Addresses</label>
                                    <div class="row g-3">
                                        <?php foreach ($addresses as $address): ?>
                                        <div class="col-md-6">
                                            <div class="card h-100 <?php echo $address['is_default'] ? 'border-primary' : ''; ?>">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <?php echo htmlspecialchars($address['address_name']); ?>
                                                        <?php if ($address['is_default']): ?>
                                                            <span class="badge" style="background-color: var(--primary);">Default</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="card-text small mb-1">
                                                        <?php echo htmlspecialchars($address['full_address']); ?>
                                                    </p>
                                                    <p class="card-text small mb-0">
                                                        <?php echo htmlspecialchars($address['city']) . ' ' . htmlspecialchars($address['postal_code']); ?>
                                                    </p>
                                                </div>
                                                <div class="card-footer bg-transparent">
                                                    <button type="button" class="btn btn-sm" style="background-color: var(--primary); color: white;" 
                                                        onclick="useAddress('<?php echo htmlspecialchars(addslashes($address['full_address'])); ?>', '<?php echo htmlspecialchars(addslashes($address['city'])); ?>', '<?php echo htmlspecialchars(addslashes($address['postal_code'])); ?>', '<?php echo addslashes($address['address_name']); ?>')">
                                                        Use This Address
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <hr class="my-4">
                                    <p class="text-muted small">Or enter a new address below:</p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="inputAddress" class="form-label">Street Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="inputAddress" name="street_address" placeholder="1234 Main St" 
                                           value="<?php echo isset($default_address) ? htmlspecialchars($default_address['full_address']) : ''; ?>" required>
                                    <div class="invalid-feedback">Please enter your street address.</div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="inputCity" class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="inputCity" name="city" 
                                               value="<?php echo isset($default_address) ? htmlspecialchars($default_address['city']) : ''; ?>" required>
                                        <div class="invalid-feedback">Please enter your city.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="inputZip" class="form-label">Zip Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="inputZip" name="zip_code" 
                                               value="<?php echo isset($default_address) ? htmlspecialchars($default_address['postal_code']) : ''; ?>" required>
                                        <div class="invalid-feedback">Please enter your zip code.</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="saveAddress" name="save_address" <?php echo isset($default_address) ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="saveAddress">Save this address for future orders</label>
                                    <div class="text-muted small" id="addressExistsWarning" style="display:<?php echo isset($default_address) ? 'block' : 'none'; ?>; color:var(--primary);">
                                        <i class="bi bi-info-circle"></i> This address already exists in your saved addresses.
                                    </div>
                                </div>
                                
                                <div id="saveAddressDetails" class="mb-3 p-3 border rounded" style="display:none; background-color: var(--light);">
                                    <div class="mb-3">
                                        <label for="addressName" class="form-label">Address Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="addressName" name="address_name" placeholder="e.g. Home, Work, etc.">
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="defaultAddress" name="default_address">
                                        <label class="form-check-label" for="defaultAddress">Set as default address</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="deliveryNotes" class="form-label">Delivery Notes & Instructions</label>
                                    <textarea class="form-control" id="deliveryNotes" name="delivery_notes" rows="4" placeholder="Any special instructions for delivery..."></textarea>
                                </div>
                            </div>

                            <!-- Delivery Date & Time Section -->
                            <div class="section-header d-flex justify-content-between align-items-center mb-0 mt-3 active" 
                                 onclick="toggleSection('deliveryDateSection', this)">
                                <h5 class="form-section-heading">
                                    <span class="section-icon"><i class="bi bi-calendar"></i></span>
                                    Delivery Date & Time
                                </h5>
                                <i class="bi bi-chevron-up" id="deliveryDateSection-icon"></i>
                            </div>
                            <div class="section-content" id="deliveryDateSection">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <label for="deliveryDate" class="form-label">Delivery Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="deliveryDate" name="delivery_date" 
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                               max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                                               value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                        <div class="form-text">Choose a delivery date (within 7 days, starting tomorrow)</div>
                                    </div>
                                </div>
                                
                                <div id="deliveryOptionsContainer" class="mb-4">
                                    <?php if (empty($delivery_options)): ?>
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle"></i> No delivery options available. Please try again later.
                                        </div>
                                    <?php else: ?>
                                    <label class="form-label">Delivery Time <span class="text-danger">*</span></label>
                                        <?php foreach ($delivery_options as $index => $option): ?>
                                        <div class="form-check mb-3 delivery-option p-3 border rounded">
                                            <input class="form-check-input delivery-option-radio" type="radio" 
                                                   name="delivery_option" id="delivery_<?php echo $option['delivery_option_id']; ?>" 
                                                   value="<?php echo $option['delivery_option_id']; ?>" 
                                                   data-fee="<?php echo $option['fee']; ?>"
                                                   data-time="<?php echo $option['time_slot']; ?>"
                                                   <?php echo $index === 0 ? 'checked' : ''; ?>>
                                            <label class="form-check-label d-block ms-2" for="delivery_<?php echo $option['delivery_option_id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($option['name']); ?></span>
                                                    <span class="badge" style="background-color: var(--primary);"><?php echo number_format($option['fee'], 0); ?> MMK</span>
                                                </div>
                                                <div class="text-muted small mt-1"><?php echo htmlspecialchars($option['description']); ?></div>
                                                <div class="text-muted small">Delivery time: <?php echo htmlspecialchars($option['formatted_time']); ?></div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Payment Method Section -->
                            <div class="section-header d-flex justify-content-between align-items-center mb-0 mt-3 active" 
                                 onclick="toggleSection('paymentMethodSection', this)">
                                <h5 class="form-section-heading">
                                    <span class="section-icon"><i class="bi bi-credit-card"></i></span>
                                    Payment Method
                                </h5>
                                <i class="bi bi-chevron-up" id="paymentMethodSection-icon"></i>
                            </div>
                            <div class="section-content" id="paymentMethodSection">
                                <div class="mb-3">
                                    <?php if ($cash_on_delivery_active): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input payment-method-radio" type="radio" 
                                               name="payment_method" id="cashOnDelivery" value="Cash on Delivery" 
                                               data-payment-id="<?php echo $cash_on_delivery_id; ?>" checked>
                                        <label class="form-check-label fw-semibold" for="cashOnDelivery">
                                            <i class="<?php echo $cash_on_delivery_icon; ?> me-1"></i> Cash on Delivery
                                        </label>
                                        <div class="text-muted small ms-4">
                                            <?php 
                                            // Look for the Cash on Delivery in the payment methods to get the description
                                            $cash_on_delivery_description = "Pay with cash when your order is delivered";
                                            foreach ($payment_methods as $method) {
                                                if ($method['payment_method'] === 'Cash on Delivery' && !empty($method['description'])) {
                                                    $cash_on_delivery_description = $method['description'];
                                                    break;
                                                }
                                            }
                                            echo $cash_on_delivery_description;
                                            ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($payment_methods as $method): ?>
                                        <?php 
                                        // Skip Cash on Delivery as it's already added above
                                        if ($method['payment_method'] === 'Cash on Delivery') continue;
                                        
                                        $payment_info_id = 'info_' . $method['id']; 
                                        ?>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input payment-method-radio" type="radio" 
                                                   name="payment_method" 
                                                   id="pm_<?php echo $method['id']; ?>" 
                                                   value="<?php echo htmlspecialchars($method['payment_method']); ?>"
                                                   data-payment-id="<?php echo $method['id']; ?>"
                                                   <?php echo (!$cash_on_delivery_active && $method === reset($payment_methods)) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-semibold" for="pm_<?php echo $method['id']; ?>">
                                                <i class="<?php echo htmlspecialchars($method['icon_class']); ?> me-1"></i> 
                                                <?php echo htmlspecialchars($method['payment_method']); ?>
                                                <?php if (!empty($method['account_phone'])): ?>
                                                <span class="small text-muted"> (Account Number: <?php echo htmlspecialchars($method['account_phone']); ?>)</span>
                                                <?php endif; ?>
                                            </label>

                                            <div class="text-muted small ms-4">
                                            <?php if (!empty($method['description'])): ?>
                                                <?php echo nl2br(htmlspecialchars($method['description'])); ?>
                                            <?php endif; ?>
                                        </div>
                                            
                                            <div class="payment-info card mt-2 ms-4 p-3" id="<?php echo $payment_info_id; ?>" style="display:none; max-width:400px;">
                                                <div class="row g-3">
                                                    
                                                    <?php if (!empty($method['account_phone'])): ?>
                                                    <div class="col-md-12">
                                                        <label for="accountPhone_<?php echo $method['id']; ?>" class="form-label">Your <?php echo htmlspecialchars($method['payment_method']); ?> Account</label>
                                                        <input type="tel" class="form-control" id="accountPhone_<?php echo $method['id']; ?>" name="account_phone" placeholder="Enter your account number">
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($method['bank_info'])): ?>
                                                    <div class="col-md-12">
                                                        <div class="card bg-light">
                                                            <div class="card-header">
                                                                <strong><i class="bi bi-bank me-1"></i> Bank Information</strong>
                                                            </div>
                                                            <div class="card-body">
                                                                <pre class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($method['bank_info']); ?></pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($method['qr_code'])): ?>
                                                    <div class="col-md-12 text-center">
                                                        <img src="<?php echo htmlspecialchars($method['qr_code']); ?>" alt="QR Code" class="img-fluid mb-2" style="max-width: 200px;">
                                                        <p class="small text-muted">Scan this QR code to make payment</p>
                                                        <p class="small fw-bold">Account: <?php echo htmlspecialchars($method['account_phone']); ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="col-md-12 mt-2">
                                                        <label for="transfer_slip_<?php echo $method['id']; ?>" class="form-label">
                                                            Upload Payment Slip <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="file" class="form-control" 
                                                               id="transfer_slip_<?php echo $method['id']; ?>" 
                                                               name="transfer_slip" 
                                                               accept="image/jpeg,image/png,image/jpg,application/pdf">
                                                        <div class="form-text">Upload your payment receipt (JPEG, PNG, or PDF)</div>
                                                        <div id="slip_preview_<?php echo $method['id']; ?>"></div>
                                                        
                                                        <!-- Hidden input for transaction ID detected by OCR -->
                                                        <input type="hidden" id="transaction_id_<?php echo $method['id']; ?>" name="transaction_id_<?php echo $method['id']; ?>">
                                                        
                                                        <!-- Scan status display -->
                                                        <div id="scan_status_<?php echo $method['id']; ?>" class="mt-2"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="button" class="btn fw-semibold btn-lg" id="placeOrderBtn" style="background-color: var(--primary); color: white;">
                                    <i class="bi bi-bag-check me-1"></i> Place Order
                                </button>
                            </div>
                            
                            <div class="d-flex justify-content-center mt-3">
                                <a href="cart.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Return to Cart
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card mb-4 shadow-sm position-sticky" style="top: 5rem;">
                    <div class="card-header" style="background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%); color: white;">
                        <h5 class="mb-0"><i class="bi bi-receipt me-1"></i> Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="mb-3 border-bottom pb-2">
                                <div class="fw-bold"><?php echo htmlspecialchars($item['meal_kit_name']); ?> <span class="badge ms-1" style="background-color: var(--primary);">x<?php echo $item['quantity']; ?></span></div>
                                
                                <div class="small text-muted">
                                    <?php if (!empty($item['ingredients'])): ?>
                                    <strong>Ingredients:</strong>
                                    <ul class="mb-1 ps-3">
                                        <?php foreach ($item['ingredients'] as $ingredient): ?>
                                            <li>
                                                <?php echo htmlspecialchars($ingredient['name']); ?>: <?php echo htmlspecialchars($ingredient['quantity']); ?>g
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>

                                    <?php if (!empty($item['customization_notes'])): ?>
                                    <div class="mt-2">
                                        <strong>Special Instructions:</strong>
                                        <div class="p-2 border-start" style="border-color: var(--primary) !important; border-width: 2px !important;">
                                            <?php echo nl2br(htmlspecialchars($item['customization_notes'])); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end"><?php echo number_format($item['total_price'], 0); ?> MMK</div>
                            </div>
                        <?php endforeach; ?>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span>Subtotal</span>
                            <span id="orderSubtotal" data-value="<?php echo $total_amount; ?>"><?php echo number_format($total_amount, 0); ?> MMK</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span>Tax (5%)</span>
                            <span id="orderTax"><?php echo number_format($tax, 0); ?> MMK</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span>Delivery Fee</span>
                            <span id="orderDeliveryFee">0 MMK</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top fw-bold">
                            <span class="fs-5">Total</span>
                            <span id="orderTotal" class="fs-5" style="color: var(--primary);"><?php echo number_format($order_total, 0); ?> MMK</span>
                        </div>
                        
                        <div class="mt-3" id="orderDeliveryNotes">
                            <!-- Delivery notes will be displayed here -->
                        </div>

                        <div class="mt-3">
                            <div class="alert p-2 mb-0 small" style="background-color: var(--light); border-left: 3px solid var(--primary);">
                                <i class="bi bi-info-circle-fill me-1" style="color: var(--primary);"></i> Select a delivery option above to see the final delivery fee and total.
                            </div>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <a href="meal-kits.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Payment Slip Scanner with Tesseract OCR -->
    <script src="assets/js/payment-slip-scanner.js"></script>
    
    <!-- Checkout JS -->
    <script src="assets/js/checkout.js"></script>
    
    <style>
        /* Loading overlay styles */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
        }
    </style>
    
    <!-- Simple Collapsible Section Script -->
    <script>
    function toggleSection(sectionId, headerElement) {
        const section = document.getElementById(sectionId);
        const icon = document.getElementById(sectionId + '-icon');
        
        if (!section || !icon) return;
        
        // Toggle active class on header
        if (headerElement) {
            if (section.classList.contains('collapsed')) {
                headerElement.classList.add('active');
            } else {
                headerElement.classList.remove('active');
            }
        }
        
        if (section.classList.contains('collapsed')) {
            // Expand the section
            section.classList.remove('collapsed');
            section.style.maxHeight = section.scrollHeight + 'px';
            icon.classList.remove('bi-chevron-down');
            icon.classList.add('bi-chevron-up');
        } else {
            // Collapse the section
            section.classList.add('collapsed');
            section.style.maxHeight = '0';
            icon.classList.remove('bi-chevron-up');
            icon.classList.add('bi-chevron-down');
        }
    }
    
    // Function to expand a section and focus on the first invalid/required field
    function expandSectionAndFocus(sectionId) {
        const section = document.getElementById(sectionId);
        const header = document.querySelector(`[onclick*="toggleSection('${sectionId}"]`);
        const icon = document.getElementById(sectionId + '-icon');
        
        if (!section || !header || !icon) return;
        
        // Add error class to the header
        header.classList.add('has-error');
        
        // If section is collapsed, expand it
        if (section.classList.contains('collapsed')) {
            // Expand the section
            section.classList.remove('collapsed');
            section.style.maxHeight = section.scrollHeight + 'px';
            icon.classList.remove('bi-chevron-down');
            icon.classList.add('bi-chevron-up');
            header.classList.add('active');
            
            // Scroll to the section
            setTimeout(() => {
                header.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
        
        // Find the first invalid or required field in the section and focus on it
        const invalidField = section.querySelector('.is-invalid');
        if (invalidField) {
            setTimeout(() => {
                invalidField.focus();
            }, 300);
            return;
        }
        
        const requiredField = section.querySelector('[required]:not([readonly]):not(:disabled)');
        if (requiredField) {
            setTimeout(() => {
                requiredField.focus();
            }, 300);
        }
    }
    
    // Initialize all sections to be expanded on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sections = document.querySelectorAll('.section-content');
        sections.forEach(section => {
            section.style.maxHeight = section.scrollHeight + 'px';
        });
        
        // Handle "Save Address" checkbox
        const saveAddressCheckbox = document.getElementById('saveAddress');
        const saveAddressDetails = document.getElementById('saveAddressDetails');
        const deliveryAddressSection = document.getElementById('deliveryAddressSection');
        
        if (saveAddressCheckbox && saveAddressDetails && deliveryAddressSection) {
            saveAddressCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    saveAddressDetails.style.display = 'block';
                } else {
                    saveAddressDetails.style.display = 'none';
                }
                
                // Update the section height after checkbox change
                setTimeout(() => {
                    deliveryAddressSection.style.maxHeight = deliveryAddressSection.scrollHeight + 'px';
                }, 10);
            });
        }
        
        // Handle payment method selection
        const paymentMethods = document.querySelectorAll('.payment-method-radio');
        const paymentSection = document.getElementById('paymentMethodSection');
        
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                // Hide all payment info sections first
                document.querySelectorAll('.payment-info').forEach(info => {
                    info.style.display = 'none';
                });
                
                // If not Cash on Delivery, show the corresponding payment info
                if (this.id !== 'cashOnDelivery') {
                    const paymentId = this.dataset.paymentId;
                    const paymentInfoId = 'info_' + paymentId;
                    const paymentInfo = document.getElementById(paymentInfoId);
                    
                    if (paymentInfo) {
                        paymentInfo.style.display = 'block';
                        
                        // Update the section height to accommodate the newly shown content
                        setTimeout(() => {
                            if (paymentSection) {
                                paymentSection.style.maxHeight = paymentSection.scrollHeight + 'px';
                            }
                        }, 100); // Increased timeout to ensure content is rendered
                    }
                }
            });
        });
        
        // Add scroll event listener to handle expanding sections when they grow
        document.querySelectorAll('.section-content').forEach(section => {
            const formElements = section.querySelectorAll('input, select, textarea');
            formElements.forEach(element => {
                element.addEventListener('focus', function() {
                    if (!section.classList.contains('collapsed')) {
                        // Update maxHeight when the content might change
                        section.style.maxHeight = section.scrollHeight + 'px';
                    }
                });
                
                // Also add input event to clear validation errors when user starts typing
                element.addEventListener('input', function() {
                    if (element.classList.contains('is-invalid')) {
                        element.classList.remove('is-invalid');
                        
                        // Check if section has any more invalid fields
                        const sectionInvalidFields = section.querySelectorAll('.is-invalid');
                        if (sectionInvalidFields.length === 0) {
                            // If no more invalid fields, remove error class from header
                            const sectionId = section.id;
                            const header = document.querySelector(`[onclick*="toggleSection('${sectionId}"]`);
                            if (header) {
                                header.classList.remove('has-error');
                            }
                        }
                    }
                });
            });
        });
        
        // Handle form submission to validate and highlight required fields
        const checkoutForm = document.getElementById('checkoutForm');
        const placeOrderBtn = document.getElementById('placeOrderBtn');
        const validationAlert = document.getElementById('validationAlert');
        
        if (placeOrderBtn && checkoutForm) {
            placeOrderBtn.addEventListener('click', function(e) {
                // Remove error styling from all headers
                document.querySelectorAll('.section-header').forEach(header => {
                    header.classList.remove('has-error');
                });
                
                // Hide the validation alert
                if (validationAlert) {
                    validationAlert.classList.remove('show');
                }
                
                // Remove any previous validation classes
                const allFields = checkoutForm.querySelectorAll('.form-control');
                allFields.forEach(field => {
                    field.classList.remove('is-invalid');
                });
                
                // Check all required fields
                const requiredFields = checkoutForm.querySelectorAll('[required]:not([readonly]):not(:disabled)');
                let firstInvalidSection = null;
                let hasErrors = false;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        hasErrors = true;
                        
                        // Find the section containing this field
                        const section = field.closest('.section-content');
                        if (section && !firstInvalidSection) {
                            firstInvalidSection = section.id;
                        }
                    }
                });
                
                // Show validation alert if there are errors
                if (hasErrors && validationAlert) {
                    validationAlert.classList.add('show');
                }
                
                // If there are validation errors, expand the first section with an error
                if (firstInvalidSection) {
                    expandSectionAndFocus(firstInvalidSection);
                    return false;
                }
                
                // Continue with form submission via the existing submitOrder function
                submitOrder();
            });
        }
    });
    </script>
</body>

</html>
