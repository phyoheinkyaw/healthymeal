<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'healthy_meal_kit';

// Create connection
try {
    $mysqli = new mysqli($host, $username, $password, $database);
    
    // Check connection
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    // Set charset to utf8mb4
    $mysqli->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
