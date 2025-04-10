<?php
// Prevent PHP from displaying errors
error_reporting(0);
ini_set('display_errors', 0);

// Set header to JSON
header('Content-Type: application/json');

// Handle errors
function handle_error($message, $error = null, $query = null) {
    $error_details = [
        'success' => false,
        'message' => $message
    ];
    if ($error) {
        $error_details['mysql_error'] = $error;
    }
    if ($query) {
        $error_details['query'] = $query;
    }
    http_response_code(500);
    echo json_encode($error_details);
    exit;
}

// Include required files
try {
    require_once '../../../includes/auth_check.php';
    require_once '../../../config/connection.php';
    
    if (!$mysqli || !$mysqli->ping()) {
        throw new Exception('Database connection failed: Could not connect to MySQL server');
    }

} catch (Exception $e) {
    handle_error('Database error: ' . $e->getMessage(), $mysqli->error);
}

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    handle_error('Unauthorized access');
}

if (!isset($_GET['id'])) {
    handle_error('Meal kit ID is required');
}

$meal_kit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$meal_kit_id) {
    handle_error('Invalid meal kit ID');
}

// Check if meal kit exists
try {
    $check_stmt = $mysqli->prepare("SELECT meal_kit_id FROM meal_kits WHERE meal_kit_id = ?");
    if (!$check_stmt) {
        throw new Exception('Database error preparing statement: ' . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $meal_kit_id);
    if (!$check_stmt->execute()) {
        throw new Exception('Database error executing query: ' . $mysqli->error);
    }
    
    $result = $check_stmt->get_result();
    if (!$result) {
        throw new Exception('Database error getting result: ' . $mysqli->error);
    }

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Meal kit not found'
        ]);
        exit;
    }

    // Check if the meal kit is referenced in any orders
    $query = "
        SELECT COUNT(*) as order_count 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE oi.meal_kit_id = ?
    ";
    
    $check_stmt = $mysqli->prepare($query);
    if (!$check_stmt) {
        throw new Exception('Database error preparing statement: ' . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $meal_kit_id);
    if (!$check_stmt->execute()) {
        throw new Exception('Database error executing query: ' . $mysqli->error);
    }
    
    $result = $check_stmt->get_result();
    if (!$result) {
        throw new Exception('Database error getting result: ' . $mysqli->error);
    }
    
    $row = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'has_orders' => $row['order_count'] > 0,
        'message' => $row['order_count'] > 0 ? 'This meal kit has active orders' : 'No active orders found'
    ]);

} catch (Exception $e) {
    handle_error('Database error: ' . $e->getMessage(), $mysqli->error);
}
