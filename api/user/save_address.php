<?php
session_start();
require_once '../../config/connection.php';

// Logging for debugging
$log_file = fopen("../../save_address_log.txt", "a");
fwrite($log_file, "Request received at " . date("Y-m-d H:i:s") . "\n");
$input_content = file_get_contents("php://input");
fwrite($log_file, "POST data: " . $input_content . "\n");
fwrite($log_file, "Session data: " . print_r($_SESSION, true) . "\n\n");
fclose($log_file);

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

// Check if user already has 6 addresses
$count_stmt = $mysqli->prepare("SELECT COUNT(*) as address_count FROM user_addresses WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$result = $count_stmt->get_result();
$address_count = $result->fetch_assoc()['address_count'];

if ($address_count >= 6) {
    echo json_encode(['success' => false, 'message' => 'You have reached the maximum limit of 6 addresses. Please delete an existing address before adding a new one.']);
    exit;
}

// Get and validate input
$data = json_decode($input_content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // If JSON parsing failed, try to use POST data
    $data = $_POST;
}

// Validate required fields
if (!isset($data['address_name']) || empty($data['address_name']) ||
    !isset($data['full_address']) || empty($data['full_address']) ||
    !isset($data['city']) || empty($data['city']) ||
    !isset($data['postal_code']) || empty($data['postal_code'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields', 'data' => $data]);
    exit;
}

// Extract address data
$address_name = $data['address_name'];
$full_address = $data['full_address'];
$city = $data['city'];
$postal_code = $data['postal_code'];
$is_default = isset($data['is_default']) && ($data['is_default'] === true || $data['is_default'] === "true" || $data['is_default'] === 1) ? 1 : 0;

// Log for debugging
$log_file = fopen("../../save_address_log.txt", "a");
fwrite($log_file, "is_default value: " . var_export($data['is_default'], true) . "\n");
fwrite($log_file, "Final is_default value: " . $is_default . "\n\n");
fclose($log_file);

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