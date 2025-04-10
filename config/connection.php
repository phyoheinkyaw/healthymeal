<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3308);
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'root');
define('DB_NAME', 'healthy_meal_kit');

// Create connection
$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

// Check connection silently
if ($mysqli->connect_error) {
    die(); // Stop execution without exposing error details
}

// Set charset to utf8mb4
$mysqli->set_charset("utf8mb4");
?> 