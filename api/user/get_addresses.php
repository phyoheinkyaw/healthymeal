<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    // Fetch user's saved addresses
    $stmt = $mysqli->prepare("
        SELECT address_id, address_name, full_address, city, postal_code, is_default
        FROM user_addresses
        WHERE user_id = ?
        ORDER BY is_default DESC, address_name ASC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $addresses = [];
    while ($address = $result->fetch_assoc()) {
        $addresses[] = [
            'id' => $address['address_id'],
            'name' => $address['address_name'],
            'full_address' => $address['full_address'],
            'city' => $address['city'],
            'postal_code' => $address['postal_code'],
            'is_default' => (bool)$address['is_default']
        ];
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