<?php
session_start();
require_once '../../../config/connection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = intval($_POST['user_id']);
$current_user_id = $_SESSION['user_id'];

// Prevent admin from deleting themselves
if ($user_id === $current_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Check if user exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        $mysqli->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    $stmt->close();

    // Delete user preferences
    $stmt = $mysqli->prepare("DELETE FROM user_preferences WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // Delete user's orders (if any)
    $stmt = $mysqli->prepare("DELETE FROM order_items WHERE order_id IN (SELECT order_id FROM orders WHERE user_id = ?)");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    $stmt = $mysqli->prepare("DELETE FROM orders WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }
    $stmt->close();

    // Delete user
    $stmt = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);

} catch (Exception $e) {
    if (isset($stmt)) {
        $stmt->close();
    }
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
} 