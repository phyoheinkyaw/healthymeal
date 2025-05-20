<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if order_id is provided
if (!isset($_POST['order_id']) || !filter_var($_POST['order_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$order_id = $_POST['order_id'];
$user_id = $_SESSION['user_id'];

// Verify order belongs to user
$check_stmt = $mysqli->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $order_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Handle file upload
if (!isset($_FILES['transfer_slip']) || $_FILES['transfer_slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($file_info, $_FILES['transfer_slip']['tmp_name']);
finfo_close($file_info);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPEG, PNG or PDF']);
    exit;
}

// Create directory if it doesn't exist
$upload_dir = '../../uploads/payment_slips/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$filename = 'payment_' . $order_id . '_' . time() . '_' . rand(1000, 9999);
$ext = pathinfo($_FILES['transfer_slip']['name'], PATHINFO_EXTENSION);
$filename .= '.' . $ext;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($_FILES['transfer_slip']['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

// Get web-accessible path
$relative_path = 'uploads/payment_slips/' . $filename;

try {
    // Get order information for the new payment record
    $orderStmt = $mysqli->prepare("
        SELECT payment_method_id, total_amount 
        FROM orders 
        WHERE order_id = ?
    ");

    if ($orderStmt) {
        $orderStmt->bind_param("i", $order_id);
        $orderStmt->execute();
        $orderResult = $orderStmt->get_result();
        if ($orderRow = $orderResult->fetch_assoc()) {
            $payment_method_id = $orderRow['payment_method_id'];
            $amount = $orderRow['total_amount'];
        }
        $orderStmt->close();
    }

    if (!isset($payment_method_id) || !isset($amount)) {
        echo json_encode(['success' => false, 'message' => 'Failed to get order information']);
        exit;
    }

    // Generate a new transaction ID
    $transaction_id = 'TXN' . time() . rand(1000, 9999);

    // Get the verification attempt count
    $attempt_stmt = $mysqli->prepare("
        SELECT COUNT(*) as total_attempts 
        FROM payment_verifications 
        WHERE order_id = ?
    ");
    $attempt_stmt->bind_param("i", $order_id);
    $attempt_stmt->execute();
    $attempt_result = $attempt_stmt->get_result();
    $attempt_data = $attempt_result->fetch_assoc();
    $attempt_count = (int)($attempt_data['total_attempts'] ?? 0) + 1;
    $attempt_stmt->close();

    // Insert new payment record
    $paymentStmt = $mysqli->prepare("
        INSERT INTO payment_history (
            order_id,
            amount,
            payment_method_id,
            transaction_id
        ) VALUES (?, ?, ?, ?)
    ");

    if ($paymentStmt) {
        $paymentStmt->bind_param("idis", 
            $order_id,
            $amount,
            $payment_method_id,
            $transaction_id
        );
        
        if ($paymentStmt->execute()) {
            $payment_id = $mysqli->insert_id;
            
            // Insert initial verification record with pending status
            $verificationStmt = $mysqli->prepare("
                INSERT INTO payment_verifications (
                    order_id,
                    payment_id,
                    transaction_id,
                    amount_verified,
                    payment_status,
                    verification_notes,
                    verified_by_id,
                    verification_attempt
                ) VALUES (?, ?, ?, ?, 0, ?, 1, ?)
            ");
            
            if ($verificationStmt) {
                $note = "Payment slip uploaded by user. Awaiting admin verification. Transaction ID: " . $transaction_id;
                
                $verificationStmt->bind_param("iisdsi", 
                    $order_id,
                    $payment_id,
                    $transaction_id,
                    $amount,
                    $note,
                    $attempt_count
                );
                
                $verificationStmt->execute();
                $verificationStmt->close();
            }
    
            // Add notification
            $notificationStmt = $mysqli->prepare("
                INSERT INTO order_notifications (
                    order_id,
                    user_id,
                    message,
                    note
                ) VALUES (?, ?, ?, ?)
            ");
            
            if ($notificationStmt) {
                $message = "Payment slip uploaded successfully. Your payment is being verified.";
                $note = "Transaction ID: " . $transaction_id;
                
                $notificationStmt->bind_param("iiss", 
                    $order_id,
                    $user_id,
                    $message,
                    $note
                );
                $notificationStmt->execute();
                $notificationStmt->close();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment slip uploaded successfully',
                'slip_preview' => $relative_path,
                'transaction_id' => $transaction_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create payment record'
            ]);
        }
        $paymentStmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $mysqli->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating order: ' . $e->getMessage()
    ]);
} 