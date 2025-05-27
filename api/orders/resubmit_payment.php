<?php
session_start();
require_once '../../config/connection.php';
require_once '../../api/payment/utils/payment_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get order ID from POST data
$order_id = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$order_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Verify user owns this order
$check_stmt = $mysqli->prepare("
    SELECT o.*, ps.payment_method 
    FROM orders o
    JOIN payment_settings ps ON o.payment_method_id = ps.id
    WHERE o.order_id = ? AND o.user_id = ?
");
$check_stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$check_stmt->execute();
$order_result = $check_stmt->get_result();

if ($order_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$order = $order_result->fetch_assoc();

// Check if payment method requires a slip (skip Cash on Delivery)
if ($order['payment_method'] === 'Cash on Delivery') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cash on Delivery orders do not require payment verification']);
    exit;
}

// Handle file upload
if (!isset($_FILES['transfer_slip']) || $_FILES['transfer_slip']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($file_info, $_FILES['transfer_slip']['tmp_name']);
finfo_close($file_info);

if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPEG, PNG or PDF']);
    exit;
}

// Create directory if it doesn't exist
$upload_dir = '../../uploads/payment_slips/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$filename = 'resubmit_' . $order_id . '_' . time() . '_' . rand(1000, 9999);
$ext = pathinfo($_FILES['transfer_slip']['name'], PATHINFO_EXTENSION);
$filename .= '.' . $ext;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($_FILES['transfer_slip']['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Get web-accessible path
$relative_path = 'uploads/payment_slips/' . $filename;

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Generate a new transaction ID
    $transaction_id = 'RESUBMIT-' . time() . '-' . rand(1000, 9999);
    
    // Get the verification attempt count
    $attempt_count = getVerificationAttemptCount($mysqli, $order_id) + 1;
    
    // Create a new payment history record
    $payment_stmt = $mysqli->prepare("
        INSERT INTO payment_history (
            order_id, 
            payment_method_id, 
            amount, 
            transaction_id, 
            payment_status
        ) VALUES (?, ?, ?, ?, 0)
    ");
    $payment_stmt->bind_param(
        "iiis", 
        $order_id,
        $order['payment_method_id'],
        $order['total_amount'],
        $transaction_id
    );
    $payment_stmt->execute();
    $payment_id = $mysqli->insert_id;
    
    // Create a verification record
    $verification_stmt = $mysqli->prepare("
        INSERT INTO payment_verifications (
            payment_id,
            order_id,
            transaction_id,
            amount_verified,
            payment_status,
            verification_notes,
            verified_by_id,
            verification_attempt,
            transfer_slip
        ) VALUES (?, ?, ?, ?, 0, ?, NULL, ?, ?)
    ");
    
    $notes = "Payment slip resubmitted by user. Verification attempt #$attempt_count.";
    
    $verification_stmt->bind_param(
        "iisdsis",
        $payment_id,
        $order_id,
        $transaction_id,
        $order['total_amount'],
        $notes,
        $attempt_count,
        $relative_path
    );
    $verification_stmt->execute();
    
    // Add notification
    $notification_message = "Payment slip resubmitted for order #{$order_id}. Awaiting verification.";
    createPaymentNotification($mysqli, $order_id, $_SESSION['user_id'], $notification_message);
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment resubmitted successfully. Please wait for verification.',
        'slip_url' => $relative_path
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    // Log the error for debugging
    error_log("Resubmit payment error for order $order_id: " . $e->getMessage());
    
    // Check for mysqli error
    if (isset($mysqli->error) && !empty($mysqli->error)) {
        error_log("MySQL error: " . $mysqli->error);
    }
    
    // Check for specific statement errors
    if (isset($payment_stmt) && $payment_stmt) {
        error_log("Payment statement error: " . $payment_stmt->error);
    }
    
    if (isset($verification_stmt) && $verification_stmt) {
        error_log("Verification statement error: " . $verification_stmt->error);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
} 