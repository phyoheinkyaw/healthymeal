<?php
// Basic DB connection test
echo "Attempting MySQL connection test...<br>";

$host = 'localhost';
$username = 'root';
$password = 'root';
$database = 'healthy_meal_kit';
$port = 3308;

try {
    // Try connecting without database first
    $conn = new mysqli($host, $username, $password, null, $port);
    
    if ($conn->connect_error) {
        die("MySQL Connection failed: " . $conn->connect_error . "<br>");
    }
    
    echo "Connected to MySQL server successfully!<br>";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE '$database'");
    
    if($result->num_rows == 0) {
        echo "Warning: Database '$database' does not exist.<br>";
    } else {
        echo "Database '$database' exists.<br>";
        
        // Try connecting to the specific database
        $conn->select_db($database);
        echo "Connected to '$database' successfully!<br>";
        
        // Test a simple query
        $tableResult = $conn->query("SHOW TABLES");
        
        if ($tableResult) {
            $tableCount = $tableResult->num_rows;
            echo "Database has $tableCount table(s).<br>";
            
            echo "<ul>";
            while ($row = $tableResult->fetch_array()) {
                echo "<li>" . $row[0] . "</li>";
            }
            echo "</ul>";
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    die("Exception: " . $e->getMessage() . "<br>");
}
?> 