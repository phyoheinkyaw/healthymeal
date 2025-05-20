<?php
session_start();
require_once '../../config/connection.php';
require_once 'utils/tax_calculator.php';

// Create a debug log file
$debug_log = fopen('../../uploads/payment_debug.log', 'a');
function debug_log($message) {
    global $debug_log;
    if ($debug_log) {
        fwrite($debug_log, date('[Y-m-d H:i:s] ') . $message . "\n");
    }
}

debug_log("--- New order submission ---");
debug_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
debug_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Output the same debug info to the browser console for development
header('Content-Type: application/json');
$debug_mode = true; // Set to false in production

if ($debug_mode) {
    // Check if PHP file upload is enabled
    $file_uploads_enabled = ini_get('file_uploads');
    $upload_max_filesize = ini_get('upload_max_filesize');
    debug_log("file_uploads enabled: " . ($file_uploads_enabled ? 'Yes' : 'No'));
    debug_log("upload_max_filesize: " . $upload_max_filesize);
    
    // Check upload directory permissions
    $upload_dir = '../../uploads/payment_slips/';
    $real_path = realpath($upload_dir);
    debug_log("Upload directory checks:");
    debug_log("Path: " . $upload_dir . ", Realpath: " . ($real_path ?: 'not found'));
    debug_log("Directory exists: " . (is_dir($upload_dir) ? 'Yes' : 'No'));
    debug_log("Directory writable: " . (is_writable($upload_dir) ? 'Yes' : 'No'));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    debug_log("Error: Not authenticated");
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];
debug_log("User ID: " . $user_id);

// Debug logging for $_FILES
if (isset($_FILES) && !empty($_FILES)) {
    debug_log("FILES data: " . print_r($_FILES, true));
} else {
    debug_log("No files in request");
}

// Debug logging for $_POST
debug_log("POST data: " . print_r($_POST, true));

// Log the $_FILES content
debug_log("FILES array: " . json_encode($_FILES));
debug_log("POST array: " . json_encode(array_keys($_POST)));

// Check if the order is submitted with a payment slip
$processed_transfer_slip = false; // Track if we've already processed the transfer slip

if (isset($_FILES['transfer_slip']) && isset($_POST['order_data'])) {
    debug_log("Found both transfer_slip in FILES and order_data in POST");
    // Get order data from the JSON string in the form data
    $data = json_decode($_POST['order_data'], true);
    debug_log("Decoded order data from POST");
    
    // Add transaction ID directly from form data if available
    if (isset($_POST['transaction_id'])) {
        $data['transaction_id'] = $_POST['transaction_id'];
        debug_log("Using transaction_id from POST: " . $_POST['transaction_id']);
    } else {
        // Look for any field that starts with transaction_id_
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'transaction_id_') === 0 && !empty($value)) {
                $data['transaction_id'] = $value;
                debug_log("Using transaction ID from field $key: $value");
                break;
            }
        }
    }
    
    // Check if file upload has occurred properly
    debug_log("File upload status: " . upload_error_to_text($_FILES['transfer_slip']['error']));
    if ($_FILES['transfer_slip']['error'] === UPLOAD_ERR_OK) {
        debug_log("Processing uploaded file: " . $_FILES['transfer_slip']['name']);
        // Process the uploaded file
        $file_info = processUploadedFile($_FILES['transfer_slip']);
        debug_log("File processing result: " . json_encode($file_info));
        
        if (!$file_info['success']) {
            debug_log("File processing failed: " . $file_info['message']);
            echo json_encode(['success' => false, 'message' => $file_info['message']]);
            fclose($debug_log);
            exit;
        }
        
        // Add file path to order data
        $data['transfer_slip'] = $file_info['path'];
        $processed_transfer_slip = true; // Mark as processed
        debug_log("Transfer slip processed successfully and added to data: " . $file_info['path']);
    } else {
        // Log file upload error
        $error_message = 'File upload error: ' . upload_error_to_text($_FILES['transfer_slip']['error']);
        debug_log($error_message);
        
        // For non-Cash on Delivery orders, require payment slip
        if (isset($data['payment_method']) && $data['payment_method'] !== 'Cash on Delivery') {
            debug_log("Payment method requires slip: " . $data['payment_method']);
            echo json_encode(['success' => false, 'message' => $error_message]);
            fclose($debug_log);
            exit;
        }
    }
} else {
    debug_log("Missing required data for file upload processing");
    if (!isset($_FILES['transfer_slip'])) {
        debug_log("No transfer_slip in FILES");
    }
    if (!isset($_POST['order_data'])) {
        debug_log("No order_data in POST");
    }
}

// Validate required fields
if (!isset($data['delivery_address']) || empty($data['delivery_address']) ||
    !isset($data['customer_phone']) || empty($data['customer_phone']) ||
    !isset($data['contact_number']) || empty($data['contact_number']) ||
    !isset($data['payment_method']) || empty($data['payment_method']) ||
    !isset($data['delivery_date']) || empty($data['delivery_date']) ||
    !isset($data['delivery_option_id']) || !filter_var($data['delivery_option_id'], FILTER_VALIDATE_INT)) {
    debug_log("Missing required fields");
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate payment transfer slip for non-Cash on Delivery orders
if ($data['payment_method'] !== 'Cash on Delivery') {
    $has_valid_slip = false;
    
    debug_log("Validating payment slip for " . $data['payment_method']);
    debug_log("Already processed slip: " . ($processed_transfer_slip ? 'Yes' : 'No'));
    
    // If we've already processed the transfer slip above, skip this check
    if ($processed_transfer_slip) {
        $has_valid_slip = true;
        debug_log("Using previously processed transfer slip");
    }
    // Check if we have a file upload that wasn't processed yet
    else if (isset($_FILES['transfer_slip']) && $_FILES['transfer_slip']['error'] === UPLOAD_ERR_OK) {
        debug_log("Processing transfer slip in validation section");
        // Process the uploaded file
        $file_info = processUploadedFile($_FILES['transfer_slip']);
        debug_log("File processing result: " . json_encode($file_info));
        
        if ($file_info['success']) {
            $data['transfer_slip'] = $file_info['path'];
            $has_valid_slip = true;
            debug_log("Transfer slip processed successfully in validation section");
        } else {
            debug_log("File processing failed in validation section: " . $file_info['message']);
        }
    } 
    // Check if we have a transfer slip path from previous processing
    else if (isset($data['transfer_slip']) && !empty($data['transfer_slip'])) {
        $has_valid_slip = true;
        debug_log("Using transfer slip path from data: " . $data['transfer_slip']);
    }
    
    if (!$has_valid_slip) {
        debug_log("No valid payment slip found, payment method requires one: " . $data['payment_method']);
        echo json_encode(['success' => false, 'message' => 'Payment slip is required for ' . $data['payment_method']]);
        fclose($debug_log);
        exit;
    }
}

// Extract order data
$delivery_address = $data['delivery_address'];
$customer_phone = $data['customer_phone'];
$contact_number = $data['contact_number'];
$delivery_notes = $data['delivery_notes'] ?? '';
$payment_method = $data['payment_method'];
$payment_method_id = $data['payment_method_id'] ?? null;

// If no payment_method_id provided but we have payment_method name, fetch the ID
if (!$payment_method_id && $payment_method) {
    $payment_stmt = $mysqli->prepare("SELECT id FROM payment_settings WHERE payment_method = ? AND is_active = 1");
    $payment_stmt->bind_param("s", $payment_method);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    if ($payment_result->num_rows > 0) {
        $payment_method_id = $payment_result->fetch_assoc()['id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
        exit;
    }
}

$account_phone = $data['account_phone'] ?? null;
$delivery_option_id = $data['delivery_option_id'];
$delivery_date = $data['delivery_date'];
$delivery_time = $data['delivery_time'] ?? null;
$transfer_slip = $data['transfer_slip'] ?? null;
$transaction_id = $data['transaction_id'] ?? null; // Extract transaction_id from form data

// Verify delivery option exists
$delivery_stmt = $mysqli->prepare("SELECT fee, time_slot FROM delivery_options WHERE delivery_option_id = ?");
$delivery_stmt->bind_param("i", $delivery_option_id);
$delivery_stmt->execute();
$delivery_result = $delivery_stmt->get_result();
if ($delivery_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid delivery option']);
    exit;
}
$delivery_option = $delivery_result->fetch_assoc();
$delivery_fee = $delivery_option['fee'];
$delivery_time = $delivery_option['time_slot'];

// Expected delivery date is the selected date
$expected_delivery_date = $delivery_date;

// Validate expected_delivery_date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected_delivery_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid delivery date format. Please select a valid date from the calendar.']);
    exit;
}

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Fetch cart items
    $cart_stmt = $mysqli->prepare("
        SELECT ci.*, mk.name as meal_kit_name
        FROM cart_items ci
        JOIN meal_kits mk ON ci.meal_kit_id = mk.meal_kit_id
        WHERE ci.user_id = ?
    ");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    if ($cart_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
        exit;
    }
    
    // Calculate totals
    $cart_items = [];
    $subtotal = 0;
    
    while ($item = $cart_result->fetch_assoc()) {
        $cart_items[] = $item;
        $subtotal += $item['total_price'];
    }
    
    // Use client-provided values when available, otherwise calculate them
    if (isset($data['subtotal']) && isset($data['tax']) && isset($data['total_amount'])) {
        // Use client-provided values
        $subtotal = filter_var($data['subtotal'], FILTER_VALIDATE_INT);
        $tax = filter_var($data['tax'], FILTER_VALIDATE_INT);
        $delivery_fee = filter_var($data['delivery_fee'], FILTER_VALIDATE_INT, ['options' => ['default' => $delivery_fee]]);
        $total_amount = filter_var($data['total_amount'], FILTER_VALIDATE_INT);
        
        // Validate the provided values for consistency
        $validation = validateTotals($subtotal, $tax, $delivery_fee, $total_amount);
        if (!$validation['valid']) {
            // Use corrected values if validation fails
            if (isset($validation['corrected_values']['tax'])) {
                $tax = $validation['corrected_values']['tax'];
            }
            if (isset($validation['corrected_values']['total_amount'])) {
                $total_amount = $validation['corrected_values']['total_amount'];
            }
        }
    } else {
        // Calculate tax using the utility function
        $tax = calculateTax($subtotal);
        
        // Calculate total amount using the utility function
        $total_amount = calculateTotal($subtotal, $delivery_fee);
    }
    
    // Generate a unique payment reference
    $payment_reference = 'PAY-REF-' . uniqid();
    
    // Create order with delivery date and time
    $order_stmt = $mysqli->prepare("
        INSERT INTO orders (
            user_id, status_id, delivery_address, contact_number, customer_phone,
            account_phone, delivery_notes, payment_method_id, payment_reference,
            delivery_fee, delivery_option_id, expected_delivery_date, 
            subtotal, tax, total_amount, preferred_delivery_time
        ) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $order_stmt->bind_param(
        "isssssisiisiiis",
        $user_id, $delivery_address, $contact_number, $customer_phone,
        $account_phone, $delivery_notes, $payment_method_id, $payment_reference,
        $delivery_fee, $delivery_option_id, $expected_delivery_date, 
        $subtotal, $tax, $total_amount, $delivery_time
    );
    $order_stmt->execute();
    $order_id = $mysqli->insert_id;
    
    // Create order items
    $item_stmt = $mysqli->prepare("
        INSERT INTO order_items (
            order_id, meal_kit_id, quantity, price_per_unit, customization_notes
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($cart_items as $item) {
        $item_stmt->bind_param(
            "iiids",
            $order_id, $item['meal_kit_id'], $item['quantity'],
            $item['single_meal_price'], $item['customization_notes']
        );
        $item_stmt->execute();
        $order_item_id = $mysqli->insert_id;
        
        // Get ingredient customizations for this cart item
        $ing_stmt = $mysqli->prepare("
            SELECT cii.* FROM cart_item_ingredients cii
            WHERE cii.cart_item_id = ?
        ");
        $ing_stmt->bind_param("i", $item['cart_item_id']);
        $ing_stmt->execute();
        $ing_result = $ing_stmt->get_result();
        
        // Create order item ingredients
        if ($ing_result->num_rows > 0) {
            $ing_insert_stmt = $mysqli->prepare("
                INSERT INTO order_item_ingredients (
                    order_item_id, ingredient_id, custom_grams
                ) VALUES (?, ?, ?)
            ");
            
            while ($ingredient = $ing_result->fetch_assoc()) {
                $ing_insert_stmt->bind_param(
                    "iid",
                    $order_item_id, $ingredient['ingredient_id'], $ingredient['quantity']
                );
                $ing_insert_stmt->execute();
            }
        }
    }
    
    // Create a payment history record
    $payment_stmt = $mysqli->prepare("
        INSERT INTO payment_history (
            order_id, amount, payment_method_id, transaction_id, payment_reference, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $payment_status = ($payment_method === 'Cash on Delivery') ? 0 : 0; // 0: pending for both
    $payment_stmt->bind_param("iiissi", $order_id, $total_amount, $payment_method_id, $transaction_id, $payment_reference, $payment_status);
    $payment_stmt->execute();
    $payment_id = $mysqli->insert_id;
    
    // For non-COD orders with transfer slip, create a payment verification record
    if ($payment_method !== 'Cash on Delivery' && isset($data['transfer_slip'])) {
        $transfer_slip = $data['transfer_slip'];
        $verification_stmt = $mysqli->prepare("
            INSERT INTO payment_verifications (
                order_id, payment_id, transaction_id, transfer_slip, amount_verified, 
                payment_status, verification_notes, verified_by_id
            ) VALUES (?, ?, ?, ?, ?, 0, ?, ?)
        ");
        $verification_notes = "Awaiting verification by admin";
        $admin_id = 1; // Default admin ID
        $verification_stmt->bind_param(
            "iissisi", 
            $order_id, $payment_id, $transaction_id, $transfer_slip, $total_amount, 
            $verification_notes, $admin_id
        );
        $verification_stmt->execute();
    }
    
    // Add notification
    $notification_stmt = $mysqli->prepare("
        INSERT INTO order_notifications (
            order_id, user_id, message, is_read
        ) VALUES (?, ?, ?, 0)
    ");
    
    if ($payment_method === 'Cash on Delivery') {
        $message = "Your order has been received and is being prepared. You will pay upon delivery.";
    } else if ($transfer_slip) {
        $message = "Your order has been received. Your payment is pending verification by our team.";
    } else {
        $message = "Your order has been received. Please complete payment to process your order.";
    }
    
    $notification_stmt->bind_param("iis", $order_id, $user_id, $message);
    $notification_stmt->execute();
    
    // Clear cart
    $clear_cart_ingredients_stmt = $mysqli->prepare("
        DELETE FROM cart_item_ingredients 
        WHERE cart_item_id IN (SELECT cart_item_id FROM cart_items WHERE user_id = ?)
    ");
    $clear_cart_ingredients_stmt->bind_param("i", $user_id);
    $clear_cart_ingredients_stmt->execute();
    
    $clear_cart_stmt = $mysqli->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $clear_cart_stmt->bind_param("i", $user_id);
    $clear_cart_stmt->execute();
    
    // Commit transaction
    $mysqli->commit();
    
    // Update session cart count
    $_SESSION['cart_count'] = 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id,
        'order_details' => [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'delivery_fee' => $delivery_fee,
            'total' => $total_amount,
            'delivery_date' => $delivery_date,
            'delivery_time' => $delivery_time
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error placing order: ' . $e->getMessage()
    ]);
}

/**
 * Process an uploaded file
 * 
 * @param array $file The $_FILES['field'] array
 * @return array Success status and file path or error message
 */
function processUploadedFile($file) {
    global $debug_log;
    // Create a debug message
    debug_log("Processing file: " . json_encode($file));
    
    // Check if the file exists
    if (!is_uploaded_file($file['tmp_name'])) {
        debug_log("Error: File not found at temporary location");
        return ['success' => false, 'message' => 'The uploaded file could not be found on the server.'];
    }
    
    // Check file size (less than 10 MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        debug_log("Error: File too large: " . $file['size'] . " bytes");
        return ['success' => false, 'message' => 'The file is too large. Maximum size is 10MB.'];
    }
    
    // Try multiple methods to get MIME type for better accuracy
    $mime_type = null;
    
    // Method 1: Use file's reported MIME type
    if (isset($file['type']) && !empty($file['type'])) {
        $mime_type = $file['type'];
        debug_log("MIME type from file data: " . $mime_type);
    }
    
    // Method 2: Use fileinfo extension
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $finfo_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($finfo_mime) {
            $mime_type = $finfo_mime;
            debug_log("MIME type from fileinfo: " . $mime_type);
        }
    }
    
    // Method 3: Try mime_content_type function
    if (!$mime_type && function_exists('mime_content_type')) {
        $mime_type = mime_content_type($file['tmp_name']);
        debug_log("MIME type from mime_content_type: " . $mime_type);
    }
    
    // If we still can't determine the MIME type, fall back to file extension
    if (!$mime_type) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        debug_log("Using file extension as fallback: " . $ext);
        
        $mime_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];
        
        $mime_type = $mime_map[$ext] ?? 'application/octet-stream';
        debug_log("Mapped MIME type from extension: " . $mime_type);
    }
    
    // Validate MIME type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($mime_type, $allowed_types)) {
        debug_log("Error: Invalid file type: " . $mime_type);
        return ['success' => false, 'message' => 'Only JPG, PNG, GIF, and PDF files are allowed.'];
    }
    
    // Create a unique filename
    $upload_dir = '../../uploads/payment_slips/';
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_filename = 'slip_' . uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . $unique_filename;
    $relative_path = 'uploads/payment_slips/' . $unique_filename;
    
    debug_log("Attempting to upload file to: " . $upload_path);
    
    // Check if directory exists and is writable
    if (!is_dir($upload_dir)) {
        debug_log("Error: Upload directory doesn't exist: " . $upload_dir);
        
        // Try to create the directory
        if (!mkdir($upload_dir, 0755, true)) {
            debug_log("Error: Failed to create upload directory");
            return ['success' => false, 'message' => 'Server configuration error: upload directory not available.'];
        }
        debug_log("Created upload directory: " . $upload_dir);
    }
    
    if (!is_writable($upload_dir)) {
        debug_log("Error: Upload directory not writable: " . $upload_dir);
        return ['success' => false, 'message' => 'Server configuration error: upload directory not writable.'];
    }
    
    // Try to move the file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        debug_log("File uploaded successfully to: " . $upload_path);
        chmod($upload_path, 0644); // Set appropriate permissions
        return ['success' => true, 'path' => $relative_path];
    } else {
        debug_log("Error: Failed to move uploaded file");
        
        // Try to copy the file as a fallback
        debug_log("Attempting to copy file as fallback");
        if (copy($file['tmp_name'], $upload_path)) {
            debug_log("File copied successfully to: " . $upload_path);
            chmod($upload_path, 0644); // Set appropriate permissions
            return ['success' => true, 'path' => $relative_path];
        }
        
        debug_log("Error details: " . error_get_last()['message']);
        return ['success' => false, 'message' => 'Failed to save the uploaded file.'];
    }
}

// Function to convert file upload error codes to text
function upload_error_to_text($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_OK:
            return "No error, file uploaded successfully (UPLOAD_ERR_OK)";
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive (UPLOAD_ERR_INI_SIZE)";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form (UPLOAD_ERR_FORM_SIZE)";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded (UPLOAD_ERR_PARTIAL)";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded (UPLOAD_ERR_NO_FILE)";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder (UPLOAD_ERR_NO_TMP_DIR)";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk (UPLOAD_ERR_CANT_WRITE)";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload (UPLOAD_ERR_EXTENSION)";
        default:
            return "Unknown upload error code: " . $error_code;
    }
}

// Close debug log when script completes
register_shutdown_function(function() {
    global $debug_log;
    if ($debug_log) {
        debug_log("--- End of order processing ---\n");
        fclose($debug_log);
    }
});

// Close the database connection
$mysqli->close(); 