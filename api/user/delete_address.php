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

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['address_id']) || !filter_var($data['address_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
    exit;
}

$address_id = $data['address_id'];

try {
    // Verify address belongs to user
    $check = $mysqli->prepare("SELECT user_id, is_default FROM user_addresses WHERE address_id = ?");
    $check->bind_param("i", $address_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Address not found']);
        exit;
    }
    
    $address = $result->fetch_assoc();
    if ($address['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Address not found']);
        exit;
    }
    
    $was_default = (bool)$address['is_default'];
    
    // Delete the address
    $stmt = $mysqli->prepare("DELETE FROM user_addresses WHERE address_id = ?");
    $stmt->bind_param("i", $address_id);
    $stmt->execute();
    
    // If this was the default address, set another address as default
    if ($was_default) {
        $stmt = $mysqli->prepare("
            UPDATE user_addresses 
            SET is_default = 1 
            WHERE user_id = ? 
            ORDER BY address_id 
            LIMIT 1
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Address deleted successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting address: ' . $e->getMessage()
    ]);
} 