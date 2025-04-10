<?php
// Database configuration
$host = 'localhost';
$port = 3308;
$username = 'root';
$password = 'root';

try {
    // Create connection without database
    $mysqli = new mysqli($host, $username, $password, '', $port);
    
    // Check connection
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }
    
    echo "Connected successfully to MySQL server<br>";
    
    // Read the SQL file
    $sql = file_get_contents('database.sql');
    
    if ($sql === false) {
        throw new Exception("Error reading SQL file");
    }
    
    // Execute the SQL commands
    if ($mysqli->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }
    
    if ($mysqli->error) {
        throw new Exception("Error executing SQL: " . $mysqli->error);
    }
    
    echo "Database created and initialized successfully!<br>";
    echo "SQL file executed successfully<br>";
    
    // Close the first connection
    $mysqli->close();
    
    // Test the connection to the new database
    $mysqli = new mysqli($host, $username, $password, 'healthy_meal_kit', $port);
    
    if ($mysqli->connect_error) {
        throw new Exception("Connection to healthy_meal_kit failed: " . $mysqli->connect_error);
    }
    
    echo "Successfully connected to the healthy_meal_kit database<br>";
    
    // Check if tables were created
    checkTables($mysqli);
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Function to check if tables were created
function checkTables($mysqli) {
    $tables = [
        'users',
        'user_preferences',
        'categories',
        'ingredients',
        'meal_kits',
        'meal_kit_ingredients',
        'orders',
        'order_items',
        'blog_posts',
        'comments',
        'health_tips',
        'remember_tokens'
    ];
    
    echo "<h3>Checking created tables:</h3>";
    foreach ($tables as $table) {
        $result = $mysqli->query("SELECT 1 FROM $table LIMIT 1");
        if ($result !== false) {
            echo "✓ Table '$table' exists and is accessible<br>";
            $result->free();
        } else {
            echo "✗ Table '$table' check failed: " . $mysqli->error . "<br>";
        }
    }
}

// Close the database connection
if (isset($mysqli)) {
    $mysqli->close();
}
?> 