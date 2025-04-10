<?php
// Prevent PHP from displaying errors
error_reporting(0);
ini_set('display_errors', 0);

// Set header to JSON
header('Content-Type: application/json');

// Handle errors
function handle_error($message, $error = null) {
    $error_details = [
        'success' => false,
        'message' => $message
    ];
    if ($error) {
        $error_details['mysql_error'] = $error;
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

// Start transaction
try {
    if (!$mysqli->begin_transaction()) {
        throw new Exception('Database error starting transaction: ' . $mysqli->error);
    }

    try {
        // First check if meal kit exists
        $check_stmt = $mysqli->prepare("SELECT meal_kit_id FROM meal_kits WHERE meal_kit_id = ?");
        if (!$check_stmt) {
            throw new Exception('Database error preparing check statement: ' . $mysqli->error);
        }
        $check_stmt->bind_param("i", $meal_kit_id);
        if (!$check_stmt->execute()) {
            throw new Exception('Database error executing check: ' . $mysqli->error);
        }
        $result = $check_stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            throw new Exception('Meal kit not found');
        }

        // Delete from cart_items first (if any)
        $delete_cart_stmt = $mysqli->prepare("DELETE FROM cart_items WHERE meal_kit_id = ?");
        if (!$delete_cart_stmt) {
            throw new Exception('Database error preparing cart items delete: ' . $mysqli->error);
        }
        $delete_cart_stmt->bind_param("i", $meal_kit_id);
        if (!$delete_cart_stmt->execute()) {
            throw new Exception('Database error deleting cart items: ' . $mysqli->error);
        }

        // Delete from order_items (if any)
        $delete_order_items_stmt = $mysqli->prepare("DELETE FROM order_items WHERE meal_kit_id = ?");
        if (!$delete_order_items_stmt) {
            throw new Exception('Database error preparing order items delete: ' . $mysqli->error);
        }
        $delete_order_items_stmt->bind_param("i", $meal_kit_id);
        if (!$delete_order_items_stmt->execute()) {
            throw new Exception('Database error deleting order items: ' . $mysqli->error);
        }

        // Finally delete the meal kit
        $delete_stmt = $mysqli->prepare("DELETE FROM meal_kits WHERE meal_kit_id = ?");
        if (!$delete_stmt) {
            throw new Exception('Database error preparing statement: ' . $mysqli->error);
        }
        $delete_stmt->bind_param("i", $meal_kit_id);
        if (!$delete_stmt->execute()) {
            throw new Exception('Database error executing delete: ' . $mysqli->error);
        }

        // Commit transaction
        if (!$mysqli->commit()) {
            throw new Exception('Database error committing transaction: ' . $mysqli->error);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Meal kit deleted successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        if (!$mysqli->rollback()) {
            throw new Exception('Database error rolling back transaction: ' . $mysqli->error);
        }
        throw $e;
    }

} catch (Exception $e) {
    handle_error('Database error: ' . $e->getMessage(), $mysqli->error);
}