<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

try {
    // Validate input
    if (!isset($_POST['email']) || !isset($_POST['password'])) {
        throw new Exception('Email and password are required');
    }

    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';

    if (!$email) {
        throw new Exception('Invalid email format');
    }

    // Get user from database
    $stmt = $mysqli->prepare("SELECT user_id, username, email, password, full_name, role FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $mysqli->error);
    }

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Invalid email or password');
    }

    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    // Handle remember me
    if ($remember) {
        // Generate a secure random token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration to 30 days from now
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Start transaction
        $mysqli->begin_transaction();

        try {
            // Delete any existing tokens for this user
            $stmt = $mysqli->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $mysqli->error);
            }
            
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            $stmt->close();
            
            // Insert new remember token
            $stmt = $mysqli->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception('Database error: ' . $mysqli->error);
            }
            
            $stmt->bind_param("iss", $user['user_id'], $token, $expires_at);
            if (!$stmt->execute()) {
                throw new Exception('Database error: ' . $stmt->error);
            }
            $stmt->close();

            $mysqli->commit();
            
            // Set cookie with token (secure, httponly, and same-site strict)
            setcookie(
                'remember_token',
                $token,
                [
                    'expires' => strtotime('+30 days'),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        } catch (Exception $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    // Set redirect URL based on role
    $redirect_url = $user['role'] === 'admin' ? '/hm/admin' : '/hm/index.php';

    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Redirecting...',
        'redirect_url' => $redirect_url,
        'user' => [
            'username' => $user['username'],
            'name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$mysqli->close(); 