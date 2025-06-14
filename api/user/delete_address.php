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

// Get input data
$data = json_decode(file_get_contents('php://input'), true);

// Validate address ID
if (!isset($data['address_id']) || empty($data['address_id'])) {
    echo json_encode(['success' => false, 'message' => 'Address ID is required']);
    exit;
}

$address_id = (int) $data['address_id'];

try {
    // First check if address belongs to this user
    $check_stmt = $mysqli->prepare("SELECT address_id FROM user_addresses WHERE address_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $address_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Address not found or you do not have permission to delete it']);
        exit;
    }
    
    // Delete the address
    $delete_stmt = $mysqli->prepare("DELETE FROM user_addresses WHERE address_id = ? AND user_id = ?");
    $delete_stmt->bind_param("ii", $address_id, $user_id);
    $delete_stmt->execute();
    
    if ($delete_stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete address']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting address: ' . $e->getMessage()
    ]);
}

// Close the database connection
$mysqli->close();
?> 