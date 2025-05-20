<?php
session_start();
require_once '../../../config/connection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

$user_id = intval($_GET['user_id']);

$query = "
    SELECT 
        u.*,
        COALESCE(up.dietary_restrictions, 'None') as dietary_restrictions,
        COALESCE(up.allergies, 'None') as allergies,
        COALESCE(up.cooking_experience, 'Not specified') as cooking_experience,
        COALESCE(up.household_size, 0) as household_size
    FROM users u
    LEFT JOIN user_preferences up ON u.user_id = up.user_id
    WHERE u.user_id = ?
";

try {
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        // Remove sensitive information
        unset($user['password']);
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
} 