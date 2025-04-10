<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get input
$currentPassword = $_POST['currentPassword'];
$newPassword = $_POST['newPassword'];
$confirmNewPassword = $_POST['confirmNewPassword'];

// Validate password match
if ($newPassword !== $confirmNewPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit;
}

// Validate password strength
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Password does not meet requirements']);
    exit;
}

try {
    // Get current user's password
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to verify current password');
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update password');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Password Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update password. Please try again.'
    ]);
}

$mysqli->close(); 