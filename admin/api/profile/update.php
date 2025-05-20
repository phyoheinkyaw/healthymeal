<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate required fields
$required_fields = ['full_name', 'username', 'email'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Sanitize and validate input
$user_id = $_SESSION['user_id'];
$full_name = filter_var(trim($_POST['full_name']), FILTER_SANITIZE_STRING);
$username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$dietary_restrictions = filter_var(trim($_POST['dietary_restrictions'] ?? ''), FILTER_SANITIZE_STRING);
$allergies = filter_var(trim($_POST['allergies'] ?? ''), FILTER_SANITIZE_STRING);
$cooking_experience = filter_var(trim($_POST['cooking_experience'] ?? 'beginner'), FILTER_SANITIZE_STRING);
$household_size = filter_var($_POST['household_size'] ?? 1, FILTER_VALIDATE_INT);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Check if username or email is already used by another user
$check_stmt = $mysqli->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
$check_stmt->bind_param("ssi", $username, $email, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username or email is already in use']);
    exit();
}

// Validate cooking experience
if (!in_array($cooking_experience, ['beginner', 'intermediate', 'advanced'])) {
    $cooking_experience = 'beginner';
}

// Validate household size
if ($household_size < 1) {
    $household_size = 1;
}

// Start transaction
$mysqli->begin_transaction();

try {
    // Update user table
    $user_stmt = $mysqli->prepare("
        UPDATE users SET 
            username = ?,
            full_name = ?,
            email = ?
        WHERE user_id = ?
    ");

    $user_stmt->bind_param("sssi", $username, $full_name, $email, $user_id);
    
    if (!$user_stmt->execute()) {
        throw new Exception("Failed to update user information");
    }

    // Update or insert user preferences
    $pref_stmt = $mysqli->prepare("
        INSERT INTO user_preferences (user_id, dietary_restrictions, allergies, cooking_experience, household_size)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            dietary_restrictions = VALUES(dietary_restrictions),
            allergies = VALUES(allergies),
            cooking_experience = VALUES(cooking_experience),
            household_size = VALUES(household_size)
    ");

    $pref_stmt->bind_param("isssi", $user_id, $dietary_restrictions, $allergies, $cooking_experience, $household_size);
    
    if (!$pref_stmt->execute()) {
        throw new Exception("Failed to update user preferences");
    }

    $mysqli->commit();

    // Update session data
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $full_name;

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error updating profile: ' . $e->getMessage()
    ]);
} 