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

        // Check for related orders
        $order_check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM order_items WHERE meal_kit_id = ?");
        $order_check_stmt->bind_param("i", $meal_kit_id);
        $order_check_stmt->execute();
        $order_check_stmt->bind_result($order_count);
        $order_check_stmt->fetch();
        $order_check_stmt->close();
        
        if ($order_count > 0) {
            // Set is_active to 0 (inactive)
            $inactive_stmt = $mysqli->prepare("UPDATE meal_kits SET is_active = 0 WHERE meal_kit_id = ?");
            $inactive_stmt->bind_param("i", $meal_kit_id);
            if (!$inactive_stmt->execute()) {
                throw new Exception('Database error setting inactive: ' . $mysqli->error);
            }
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => 'Meal kit cannot be deleted because it has related orders. Meal kit set to inactive.']);
            exit;
        }

        // No related orders: try to delete meal kit and all related records
        try {
            // Delete from cart_items first (if any)
            $delete_cart_stmt = $mysqli->prepare("DELETE FROM cart_items WHERE meal_kit_id = ?");
            $delete_cart_stmt->bind_param("i", $meal_kit_id);
            $delete_cart_stmt->execute();

            // Delete from meal_kit_ingredients
            $delete_ingredients_stmt = $mysqli->prepare("DELETE FROM meal_kit_ingredients WHERE meal_kit_id = ?");
            $delete_ingredients_stmt->bind_param("i", $meal_kit_id);
            $delete_ingredients_stmt->execute();

            // Get image filename before deleting meal kit
            $img_stmt = $mysqli->prepare("SELECT image_url FROM meal_kits WHERE meal_kit_id = ?");
            $img_stmt->bind_param("i", $meal_kit_id);
            $img_stmt->execute();
            $img_stmt->bind_result($image_url);
            $img_stmt->fetch();
            $img_stmt->close();

            // Finally delete the meal kit
            $delete_stmt = $mysqli->prepare("DELETE FROM meal_kits WHERE meal_kit_id = ?");
            $delete_stmt->bind_param("i", $meal_kit_id);
            if (!$delete_stmt->execute()) {
                // If delete fails for any reason, set inactive
                $inactive_stmt = $mysqli->prepare("UPDATE meal_kits SET is_active = 0 WHERE meal_kit_id = ?");
                $inactive_stmt->bind_param("i", $meal_kit_id);
                $inactive_stmt->execute();
                $mysqli->commit();
                echo json_encode(['success' => true, 'message' => 'Meal kit could not be deleted due to an error. Meal kit set to inactive.']);
                exit;
            }

            // Unlink uploaded image file if it is not a remote URL and not empty
            if (!empty($image_url) && !preg_match('/^https?:\/\//i', $image_url)) {
                $img_path = realpath(__DIR__ . '/../../../uploads/meal-kits/' . $image_url);
                if ($img_path && file_exists($img_path)) {
                    unlink($img_path);
                }
            }

            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => 'Meal kit deleted successfully']);
            exit;
        } catch (Exception $e) {
            // If any error occurs, set inactive
            $inactive_stmt = $mysqli->prepare("UPDATE meal_kits SET is_active = 0 WHERE meal_kit_id = ?");
            $inactive_stmt->bind_param("i", $meal_kit_id);
            $inactive_stmt->execute();
            $mysqli->commit();
            echo json_encode(['success' => true, 'message' => 'Meal kit could not be deleted due to an error. Meal kit set to inactive.']);
            exit;
        }

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