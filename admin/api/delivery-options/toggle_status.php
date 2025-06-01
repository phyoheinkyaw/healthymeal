<?php
require_once '../../../includes/auth_check.php';
header('Content-Type: application/json');

$role = checkRememberToken();
if (!$role || $role != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON data or form data
$data = $_POST;
if (empty($data)) {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
}

if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Delivery option ID is required']);
    exit();
}

$id = (int)$data['id'];

// Toggle active status (1 - is_active flips the value)
$stmt = $mysqli->prepare("UPDATE delivery_options SET is_active = 1 - is_active WHERE delivery_option_id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Get updated status
    $stmt->close();
    
    $status_stmt = $mysqli->prepare("SELECT is_active FROM delivery_options WHERE delivery_option_id = ?");
    $status_stmt->bind_param("i", $id);
    $status_stmt->execute();
    $status_stmt->bind_result($is_active);
    $status_stmt->fetch();
    $status_stmt->close();
    
    $status_text = $is_active ? 'Active' : 'Inactive';
    $message = "Delivery option set to " . strtolower($status_text);
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'data' => [
            'id' => $id,
            'is_active' => $is_active,
            'status_text' => $status_text
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}

?> 