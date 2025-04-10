<?php
session_start();
require_once '../../config/connection.php';

// Clear remember me token if exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    // Delete token from database
    $stmt = $mysqli->prepare("DELETE FROM remember_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    // Delete cookie
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Destroy session
session_destroy();

// Redirect to home page
header("Location: ../../index.php");
exit();
?> 