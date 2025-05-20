<?php
require_once '../../includes/auth_check.php';
require_once '../../config/connection.php';

$role = checkRememberToken();
if (!$role || $role != 1) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Payment method ID is required']);
    exit();
}

$id = (int)$_GET['id'];

// Get payment method details
$stmt = $mysqli->prepare("SELECT description, bank_info FROM payment_settings WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Payment method not found']);
    exit();
}

$payment_method = $result->fetch_assoc();
$stmt->close();

// Return payment method details
header("Content-Type: application/json");
echo json_encode([
    'success' => true,
    'description' => $payment_method['description'],
    'bank_info' => $payment_method['bank_info']
]); 