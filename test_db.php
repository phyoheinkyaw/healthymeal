<?php
require_once 'config/connection.php';

// Check table structure for payment_history
$result = $mysqli->query("SHOW COLUMNS FROM payment_history");
echo "<h2>payment_history table structure:</h2>";
if ($result) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";
} else {
    echo "Error fetching payment_history table structure: " . $mysqli->error;
}

// Check table structure for payment_verifications
$result = $mysqli->query("SHOW COLUMNS FROM payment_verifications");
echo "<h2>payment_verifications table structure:</h2>";
if ($result) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre>";
} else {
    echo "Error fetching payment_verifications table structure: " . $mysqli->error;
}

// Close the database connection
$mysqli->close();
?> 