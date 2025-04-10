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

// Validate required fields
$required_fields = ['username', 'email', 'password', 'full_name', 'role'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit();
    }
}

try {
    // Sanitize and validate input
    $username = $mysqli->real_escape_string($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $mysqli->real_escape_string($_POST['full_name']);
    $role = $mysqli->real_escape_string($_POST['role']);

    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    if (!in_array($role, ['user', 'admin'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit();
    }

    // Start transaction
    $mysqli->begin_transaction();

    // Check if username or email already exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('ss', $username, $email);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        $mysqli->rollback();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit();
    }
    $stmt->close();

    // Insert new user
    $stmt = $mysqli->prepare("
        INSERT INTO users (username, email, password, full_name, role, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('sssss', $username, $email, $password, $full_name, $role);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $user_id = $stmt->insert_id;
    $stmt->close();

    // Create empty user preferences
    $stmt = $mysqli->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'User added successfully']);

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