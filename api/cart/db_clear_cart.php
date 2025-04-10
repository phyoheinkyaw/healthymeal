<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to clear your cart']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $mysqli->begin_transaction();
    
    // Delete all cart items for this user
    $stmt = $mysqli->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Commit transaction
    $mysqli->commit();
    
    // Update session cart count
    $_SESSION['cart_count'] = 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully',
        'total_items' => 0
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($mysqli) && $mysqli->errno) {
        $mysqli->rollback();
    }
    
    error_log("Error clearing cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while clearing your cart']);
}

// Close the database connection
$mysqli->close();
?>
