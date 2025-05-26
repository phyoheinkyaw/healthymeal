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
if (!isset($data['address_id']) || empty($data['address_id']) ||
    !isset($data['address_name']) || empty($data['address_name']) ||
    !isset($data['full_address']) || empty($data['full_address']) ||
    !isset($data['city']) || empty($data['city']) ||
    !isset($data['postal_code']) || empty($data['postal_code'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Extract address data
$address_id = (int)$data['address_id'];
$address_name = $data['address_name'];
$full_address = $data['full_address'];
$city = $data['city'];
$postal_code = $data['postal_code'];
$is_default = isset($data['is_default']) && ($data['is_default'] === true || $data['is_default'] === "true" || $data['is_default'] === 1) ? 1 : 0;

// Log for debugging
$log_file = fopen("../../update_address_log.txt", "a");
fwrite($log_file, "Request received at " . date("Y-m-d H:i:s") . "\n");
fwrite($log_file, "is_default value: " . var_export($data['is_default'], true) . "\n");
fwrite($log_file, "Final is_default value: " . $is_default . "\n\n");
fclose($log_file);

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // First check if address belongs to this user
    $check_stmt = $mysqli->prepare("SELECT address_id FROM user_addresses WHERE address_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $address_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Address not found or you do not have permission to update it']);
        $mysqli->rollback();
        exit;
    }
    
    // If this address is set as default, update all other addresses to not be default
    if ($is_default) {
        $update_stmt = $mysqli->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
    }
    
    // Update the address
    $stmt = $mysqli->prepare("
        UPDATE user_addresses 
        SET address_name = ?, full_address = ?, city = ?, postal_code = ?, is_default = ?
        WHERE address_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ssssiis", $address_name, $full_address, $city, $postal_code, $is_default, $address_id, $user_id);
    $stmt->execute();
    
    // Check if anything was updated
    if ($stmt->affected_rows === 0 && $stmt->errno === 0) {
        // No changes were made but no error occurred (data was the same)
        $mysqli->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Address is already up to date'
        ]);
    } else if ($stmt->affected_rows > 0) {
        // Address was updated successfully
        $mysqli->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Address updated successfully'
        ]);
    } else {
        // Error occurred
        $mysqli->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update address: ' . $stmt->error
        ]);
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error updating address: ' . $e->getMessage()
    ]);
}

// Close the database connection
$mysqli->close();
?> 