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

// Validate required fields
$required_fields = ['user_id', 'username', 'full_name', 'email', 'role'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit();
    }
}

try {
    // Sanitize and validate input
    $user_id = intval($_POST['user_id']);
    $username = $mysqli->real_escape_string($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $full_name = $mysqli->real_escape_string($_POST['full_name']);
    $role = $mysqli->real_escape_string($_POST['role']);
    
    // Optional fields with default values matching the database schema
    $dietary_restrictions = isset($_POST['dietary_restrictions']) ? $mysqli->real_escape_string($_POST['dietary_restrictions']) : null;
    $allergies = isset($_POST['allergies']) ? $mysqli->real_escape_string($_POST['allergies']) : null;
    
    // Convert cooking_experience to numeric value
    $cooking_experience = 0; // Default to beginner
    if (isset($_POST['cooking_experience'])) {
        if ($_POST['cooking_experience'] === 'intermediate') {
            $cooking_experience = 1;
        } elseif ($_POST['cooking_experience'] === 'advanced') {
            $cooking_experience = 2;
        } elseif (is_numeric($_POST['cooking_experience'])) {
            $cooking_experience = intval($_POST['cooking_experience']);
            if ($cooking_experience < 0 || $cooking_experience > 2) {
                $cooking_experience = 0; // Reset to beginner if out of range
            }
        }
    }
    
    $household_size = isset($_POST['household_size']) && $_POST['household_size'] !== '' ? intval($_POST['household_size']) : 1;

    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    if (!in_array($role, [0, 1])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit();
    }

    // Start transaction
    $mysqli->begin_transaction();

    // Check if username or email exists for other users
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('ssi', $username, $email, $user_id);
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

    // Build the update query
    $query = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?";
    $types = "ssss";
    $params = [$username, $full_name, $email, $role];

    // Add password to update if provided
    if (!empty($_POST['password'])) {
        $query .= ", password = ?";
        $types .= "s";
        $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    $query .= " WHERE user_id = ?";
    $types .= "i";
    $params[] = $user_id;

    // Update user
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    // Check if user exists (if no rows were updated and there was no error)
    if ($stmt->affected_rows === 0 && $stmt->errno !== 0) {
        $stmt->close();
        $mysqli->rollback();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    $stmt->close();

    // First check if user preferences exist
    $stmt = $mysqli->prepare("SELECT preference_id FROM user_preferences WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        // Insert new preferences if they don't exist
        $stmt = $mysqli->prepare("
            INSERT INTO user_preferences 
            (user_id, dietary_restrictions, allergies, cooking_experience, household_size) 
            VALUES (?, ?, ?, ?, ?)
        ");
    } else {
        // Update existing preferences
        $stmt = $mysqli->prepare("
            UPDATE user_preferences 
            SET dietary_restrictions = ?,
                allergies = ?,
                cooking_experience = ?,
                household_size = ?
            WHERE user_id = ?
        ");
    }

    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    if ($result->num_rows === 0) {
        $stmt->bind_param('isssi', $user_id, $dietary_restrictions, $allergies, $cooking_experience, $household_size);
    } else {
        $stmt->bind_param('sssii', $dietary_restrictions, $allergies, $cooking_experience, $household_size, $user_id);
    }

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);

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