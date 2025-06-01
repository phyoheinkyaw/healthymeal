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

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit();
}

// Required fields
$required_fields = ['delivery_option_id', 'name', 'fee', 'time_slot', 'cutoff_time', 'max_orders_per_slot'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => 'Required field missing: ' . $field]);
        exit();
    }
}

$id = (int)$data['delivery_option_id'];
$name = $data['name'];
$description = $data['description'] ?? '';
$fee = (int)$data['fee'];
$time_slot = $data['time_slot'];
$cutoff_time = $data['cutoff_time'];
$max_orders = (int)$data['max_orders_per_slot'];
$is_active = isset($data['is_active']) ? 1 : 0;

// Update delivery option
$stmt = $mysqli->prepare("UPDATE delivery_options SET name = ?, description = ?, fee = ?, time_slot = ?, cutoff_time = ?, max_orders_per_slot = ?, is_active = ? WHERE delivery_option_id = ?");
$stmt->bind_param("ssissiii", $name, $description, $fee, $time_slot, $cutoff_time, $max_orders, $is_active, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Delivery option updated successfully',
        'data' => [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'fee' => $fee,
            'time_slot' => $time_slot,
            'time_slot_formatted' => date('g:i A', strtotime($time_slot)),
            'cutoff_time' => $cutoff_time,
            'cutoff_time_formatted' => date('g:i A', strtotime($cutoff_time)),
            'max_orders_per_slot' => $max_orders,
            'is_active' => $is_active
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update delivery option']);
}

$stmt->close();
?> 