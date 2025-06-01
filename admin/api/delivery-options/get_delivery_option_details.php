<?php
require_once '../../../includes/auth_check.php';

$role = checkRememberToken();
if (!$role || $role != 1) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Delivery option ID is required']);
    exit();
}

$delivery_id = (int)$_GET['id'];

// Query the database for delivery option details
$stmt = $mysqli->prepare("SELECT * FROM delivery_options WHERE delivery_option_id = ?");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Format times for display
    $row['time_slot_formatted'] = date('g:i A', strtotime($row['time_slot']));
    $row['cutoff_time_formatted'] = date('g:i A', strtotime($row['cutoff_time']));
    
    header('Content-Type: application/json');
    echo json_encode($row);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Delivery option not found']);
}

$stmt->close();
?> 