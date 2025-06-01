<?php
require_once '../../../includes/auth_check.php';

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set header to JSON
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

// Validate ID
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid delivery option ID']);
    exit();
}

// Update status in database
$stmt = $mysqli->prepare("UPDATE delivery_options SET is_active = ? WHERE delivery_option_id = ?");
$stmt->bind_param('ii', $status, $id);

if ($stmt->execute()) {
    // Check if updating an option with active orders if deactivating
    if ($status == 0) {
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM orders WHERE delivery_option_id = ? AND status_id IN (1, 2)"); // Pending or Processing
        $check_stmt->bind_param('i', $id);
        $check_stmt->execute();
        $check_stmt->bind_result($active_orders);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($active_orders > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Status updated, but this option has ' . $active_orders . ' active orders that will be affected.',
                'warning' => true,
                'active_orders' => $active_orders
            ]);
            exit();
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Delivery option status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update delivery option status: ' . $mysqli->error]);
}

$stmt->close(); 