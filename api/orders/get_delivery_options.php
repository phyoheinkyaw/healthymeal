<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

try {
    // Get current date and time
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    
    // Fetch all available delivery options
    $stmt = $mysqli->prepare("
        SELECT delivery_option_id, name, description, fee, 
               TIME_FORMAT(time_slot, '%h:%i %p') as formatted_time_slot,
               TIME_FORMAT(cutoff_time, '%h:%i %p') as formatted_cutoff_time,
               time_slot, cutoff_time, max_orders_per_slot
        FROM delivery_options
        WHERE is_active = 1
        ORDER BY time_slot ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $delivery_options = [];
    while ($option = $result->fetch_assoc()) {
        // Check if this time slot is available for today
        $available_today = true;
        
        // If current time is past cutoff time for this option, it's not available for today
        if (strtotime($current_time) > strtotime($option['cutoff_time'])) {
            $available_today = false;
        }
        
        // Check if this slot has reached max orders for today
        $order_count_stmt = $mysqli->prepare("
            SELECT COUNT(*) as order_count
            FROM orders 
            WHERE delivery_option_id = ? 
              AND expected_delivery_date = ?
        ");
        $order_count_stmt->bind_param("is", $option['delivery_option_id'], $current_date);
        $order_count_stmt->execute();
        $order_count_result = $order_count_stmt->get_result();
        $order_count = $order_count_result->fetch_assoc()['order_count'];
        
        if ($order_count >= $option['max_orders_per_slot']) {
            $available_today = false;
        }
        
        $delivery_options[] = [
            'id' => $option['delivery_option_id'],
            'name' => $option['name'],
            'description' => $option['description'],
            'fee' => (float)$option['fee'],
            'time_slot' => $option['formatted_time_slot'],
            'available_today' => $available_today,
            'cutoff_time' => $option['formatted_cutoff_time']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'delivery_options' => $delivery_options
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching delivery options: ' . $e->getMessage()
    ]);
} 