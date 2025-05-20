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

// Get user ID
$user_id = $_SESSION['user_id'];

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['address_name']) || empty($data['address_name']) ||
    !isset($data['full_address']) || empty($data['full_address']) ||
    !isset($data['city']) || empty($data['city']) ||
    !isset($data['postal_code']) || empty($data['postal_code'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Extract address data
$address_name = $data['address_name'];
$full_address = $data['full_address'];
$city = $data['city'];
$postal_code = $data['postal_code'];
$is_default = isset($data['is_default']) && $data['is_default'] ? 1 : 0;

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // If this address is set as default, update all other addresses to not be default
    if ($is_default) {
        $update_stmt = $mysqli->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
    }
    
    // Insert new address
    $stmt = $mysqli->prepare("
        INSERT INTO user_addresses (user_id, address_name, full_address, city, postal_code, is_default)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssi", $user_id, $address_name, $full_address, $city, $postal_code, $is_default);
    $stmt->execute();
    
    $address_id = $mysqli->insert_id;
    
    // Commit transaction
    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Address saved successfully',
        'address_id' => $address_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving address: ' . $e->getMessage()
    ]);
}

// Close the database connection
$mysqli->close(); 