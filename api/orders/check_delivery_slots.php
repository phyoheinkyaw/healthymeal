<?php
session_start();
require_once '../../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

// Get JSON data
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate input
if (!isset($data['delivery_date']) || empty($data['delivery_date'])) {
    echo json_encode(['success' => false, 'message' => 'Delivery date is required']);
    exit;
}

$delivery_date = $data['delivery_date'];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $delivery_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Expected YYYY-MM-DD']);
    exit;
}

try {
    // Get current cutoff time for today's orders
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    
    // Fetch all delivery options
    $options_stmt = $mysqli->prepare("
        SELECT delivery_option_id, name, fee, time_slot, cutoff_time, max_orders_per_slot
        FROM delivery_options
        WHERE is_active = 1
        ORDER BY time_slot ASC
    ");
    
    if (!$options_stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $options_stmt->execute();
    $delivery_options_result = $options_stmt->get_result();
    
    $slot_availability = [];
    
    // For each delivery option, check how many orders we have for that date
    while ($option = $delivery_options_result->fetch_assoc()) {
        $option_id = $option['delivery_option_id'];
        
        // Count orders for this option on the selected date
        $count_stmt = $mysqli->prepare("
            SELECT COUNT(*) as order_count
            FROM orders
            WHERE delivery_option_id = ?
            AND expected_delivery_date = ?
        ");
        
        if (!$count_stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        
        $count_stmt->bind_param('is', $option_id, $delivery_date);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $order_count = $count_row['order_count'];
        $count_stmt->close();
        
        // Check cutoff time if delivery date is today
        $is_available = true;
        if ($delivery_date == $current_date && strtotime($current_time) > strtotime($option['cutoff_time'])) {
            $is_available = false;
        }
        
        // Add availability info to the response
        $slot_availability[] = [
            'delivery_option_id' => $option_id,
            'order_count' => $order_count,
            'max_orders' => $option['max_orders_per_slot'],
            'is_available' => $is_available && ($order_count < $option['max_orders_per_slot'])
        ];
    }
    
    $options_stmt->close();
    
    echo json_encode([
        'success' => true,
        'delivery_date' => $delivery_date,
        'slot_availability' => $slot_availability
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 