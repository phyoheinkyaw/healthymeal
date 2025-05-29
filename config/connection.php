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

// Trigger the inactive users check approximately once per day
// Only do this for a small percentage of requests to avoid performance impact
if (rand(1, 1000) === 1) { // 0.1% chance of running on any page load
    // Check if the inactive users script exists
    $inactive_script = dirname(__DIR__) . '/cron/check_inactive_users.php';
    if (file_exists($inactive_script)) {
        // Run the script without showing output
        $suppress_output = true;
        require_once $inactive_script;
        
        // Only run if it hasn't been run today
        if (shouldRunToday($mysqli)) {
            // Run the inactive users check in the background
            checkInactiveUsers($mysqli, true);
        }
    }
}
?> 