<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../config/connection.php';

/**
 * Check if a valid remember token cookie exists and log in the user if so
 * @return mixed User role if token is valid, false otherwise
 */
function checkRememberToken() {
    global $mysqli;
    
    // If user is already logged in, return their role
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['role'];
    }
    
    // Check if remember token cookie exists
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    
    // Get token from database
    $stmt = $mysqli->prepare("
        SELECT rt.user_id, rt.expires_at, u.username, u.full_name, u.role
        FROM remember_tokens rt
        JOIN users u ON rt.user_id = u.user_id
        WHERE rt.token = ? AND rt.expires_at > NOW()
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("s", $token);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $token_data = $result->fetch_assoc();
    $stmt->close();
    
    // If token is valid and not expired
    if ($token_data) {
        // Set session variables
        $_SESSION['user_id'] = $token_data['user_id'];
        $_SESSION['username'] = $token_data['username'];
        $_SESSION['full_name'] = $token_data['full_name'];
        $_SESSION['role'] = $token_data['role'];
        
        // Refresh token expiration
        $new_expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $mysqli->prepare("UPDATE remember_tokens SET expires_at = ? WHERE token = ?");
        
        if ($stmt) {
            $stmt->bind_param("ss", $new_expires, $token);
            $stmt->execute();
            $stmt->close();
            
            // Refresh cookie
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
        }
        
        // Update last_login_at
        $user_id = $_SESSION['user_id'];
        $update_login = $mysqli->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
        if ($update_login) {
            $update_login->bind_param("i", $user_id);
            $update_login->execute();
            $update_login->close();
        }
        
        return $token_data['role'];
    }
    
    // Token is invalid or expired, remove it
    setcookie('remember_token', '', time() - 3600, '/');
    $stmt = $mysqli->prepare("DELETE FROM remember_tokens WHERE token = ?");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }
    
    return false;
}

/**
 * Clean up expired remember tokens
 */
function cleanupExpiredTokens() {
    global $mysqli;
    $mysqli->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");
}

// Run cleanup occasionally (1% chance on each request)
if (rand(1, 100) === 1) {
    cleanupExpiredTokens();
} 