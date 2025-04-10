<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get and sanitize input
$firstName = htmlspecialchars(trim($_POST['firstName']));
$lastName = htmlspecialchars(trim($_POST['lastName']));
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$password = $_POST['password'];

// Generate username from email (everything before @)
$username = strtolower(explode('@', $email)[0]);

// Debug log
error_log("Registration attempt - Email: " . $email . ", Username: " . $username);

// Validate inputs
if (empty($firstName) || empty($lastName)) {
    echo json_encode(['success' => false, 'message' => 'Name fields cannot be empty']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password strength
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password does not meet requirements']);
    exit;
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Check if email already exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("ss", $email, $username);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email or username already registered']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Create full name
    $fullName = $firstName . ' ' . $lastName;
    
    // Insert new user
    $stmt = $mysqli->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'user')");
    if (!$stmt) {
        throw new Exception('Prepare user insert failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("ssss", $username, $email, $hashedPassword, $fullName);
    
    if (!$stmt->execute()) {
        throw new Exception('User insert failed: ' . $stmt->error);
    }
    
    $userId = $mysqli->insert_id;
    
    // Create user preferences record
    $stmt = $mysqli->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
    if (!$stmt) {
        throw new Exception('Prepare preferences insert failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Preferences insert failed: ' . $stmt->error);
    }
    
    // Commit transaction
    $mysqli->commit();
    
    // Set session variables for auto-login
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $fullName;
    $_SESSION['role'] = 'user';
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Redirecting...',
        'user' => [
            'username' => $username,
            'name' => $fullName,
            'role' => 'user'
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    error_log("Registration Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Registration failed: ' . $e->getMessage()
    ]);
}

$mysqli->close(); 