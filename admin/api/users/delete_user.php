<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../../config/connection.php';

header('Content-Type: application/json');

function debug_log($msg) {
    file_put_contents(__DIR__ . '/delete_user_debug.log', date('c') . ' ' . $msg . "\n", FILE_APPEND);
}

debug_log('Delete user called. POST: ' . json_encode($_POST) . ' SESSION: ' . json_encode($_SESSION));

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    debug_log('Unauthorized access');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['user_id'])) {
    debug_log('User ID not set');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = intval($_POST['user_id']);
$current_user_id = $_SESSION['user_id'];

debug_log('Attempting to delete user_id: ' . $user_id . ', current_user_id: ' . $current_user_id);

if ($user_id === $current_user_id) {
    debug_log('Tried to delete own account');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
    exit();
}

$stmt = null;
try {
    $mysqli->begin_transaction();

    // Delete comments by user
    $stmt = $mysqli->prepare('DELETE FROM comments WHERE user_id = ?');
    if (!$stmt) { debug_log('Prepare failed (comments): ' . $mysqli->error); throw new Exception($mysqli->error); }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    debug_log('Deleted from comments');
    $stmt->close(); $stmt = null;

    // Get all order_ids for this user
    $order_ids = [];
    $stmt = $mysqli->prepare('SELECT order_id FROM orders WHERE user_id = ?');
    if (!$stmt) { debug_log('Prepare failed (orders): ' . $mysqli->error); throw new Exception($mysqli->error); }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $order_ids[] = $row['order_id'];
    }
    $stmt->close(); $stmt = null;
    debug_log('Order IDs: ' . json_encode($order_ids));

    if (!empty($order_ids)) {
        // Get all order_item_ids for these orders
        $order_item_ids = [];
        $in = str_repeat('?,', count($order_ids) - 1) . '?';
        $stmt = $mysqli->prepare("SELECT order_item_id FROM order_items WHERE order_id IN ($in)");
        if (!$stmt) { debug_log('Prepare failed (order_items): ' . $mysqli->error); throw new Exception($mysqli->error); }
        $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $order_item_ids[] = $row['order_item_id'];
        }
        $stmt->close(); $stmt = null;
        debug_log('Order Item IDs: ' . json_encode($order_item_ids));

        // Delete from order_item_ingredients
        if (!empty($order_item_ids)) {
            $in2 = str_repeat('?,', count($order_item_ids) - 1) . '?';
            $stmt = $mysqli->prepare("DELETE FROM order_item_ingredients WHERE order_item_id IN ($in2)");
            if (!$stmt) { debug_log('Prepare failed (order_item_ingredients): ' . $mysqli->error); throw new Exception($mysqli->error); }
            $stmt->bind_param(str_repeat('i', count($order_item_ids)), ...$order_item_ids);
            $stmt->execute();
            debug_log('Deleted from order_item_ingredients');
            $stmt->close(); $stmt = null;
        }

        // Delete from order_items
        $stmt = $mysqli->prepare("DELETE FROM order_items WHERE order_id IN ($in)");
        if (!$stmt) { debug_log('Prepare failed (order_items delete): ' . $mysqli->error); throw new Exception($mysqli->error); }
        $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
        $stmt->execute();
        debug_log('Deleted from order_items');
        $stmt->close(); $stmt = null;

        // Delete from orders
        $stmt = $mysqli->prepare("DELETE FROM orders WHERE order_id IN ($in)");
        if (!$stmt) { debug_log('Prepare failed (orders delete): ' . $mysqli->error); throw new Exception($mysqli->error); }
        $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
        $stmt->execute();
        debug_log('Deleted from orders');
        $stmt->close(); $stmt = null;
    }

    // Delete from user_preferences
    $stmt = $mysqli->prepare('DELETE FROM user_preferences WHERE user_id = ?');
    if (!$stmt) { debug_log('Prepare failed (user_preferences): ' . $mysqli->error); throw new Exception($mysqli->error); }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    debug_log('Deleted from user_preferences');
    $stmt->close(); $stmt = null;

    // Delete from users
    $stmt = $mysqli->prepare('DELETE FROM users WHERE user_id = ?');
    if (!$stmt) { debug_log('Prepare failed (users): ' . $mysqli->error); throw new Exception($mysqli->error); }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    debug_log('Deleted from users');
    $stmt->close(); $stmt = null;

    $mysqli->commit();
    debug_log('User deleted successfully');
    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
} catch (Exception $e) {
    if ($stmt && $stmt instanceof mysqli_stmt) {
        @$stmt->close();
        $stmt = null;
    }
    $mysqli->rollback();
    debug_log('Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if ($stmt && $stmt instanceof mysqli_stmt) {
        @$stmt->close();
        $stmt = null;
    }
}