<?php
// Prevent direct access
if (!defined('BASE_PATH') && basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__)) {
    exit('No direct script access allowed');
}

// Silence errors for this file only
error_reporting(0);

// Database configuration
$host = 'localhost';
$username = 'root';
$password = 'root';
$database = 'healthy_meal_kit';
$port = 3308; // Custom port

// Create connection
$mysqli = new mysqli($host, $username, $password, $database, $port);

// Silence connection errors
if ($mysqli->connect_error) {
    // Save error to log file instead of displaying it
    error_log("Database connection failed: " . $mysqli->connect_error);
} else {
    // Set charset
    $mysqli->set_charset("utf8mb4");
}

// Define BASE_PATH if not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
?>
