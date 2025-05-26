<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

try {
    // Fetch user's saved addresses
    $stmt = $mysqli->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC, address_name ASC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $addresses = [];
    while ($address = $result->fetch_assoc()) {
        $addresses[] = $address;
    }
    
    echo json_encode([
        'success' => true,
        'addresses' => $addresses
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching addresses: ' . $e->getMessage()
    ]);
}

// Close the database connection
$mysqli->close(); 