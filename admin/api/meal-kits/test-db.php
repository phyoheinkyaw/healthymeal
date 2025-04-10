<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../config/connection.php';

// Test connection
if (!$mysqli || !$mysqli->ping()) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Database connection successful!\n";

// Test tables
$tables = ['meal_kits', 'orders', 'order_items'];
foreach ($tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "Table $table exists\n";
    } else {
        echo "Table $table does NOT exist\n";
    }
}

// Test meal_kit_id 1
$meal_kit_id = 1;
$result = $mysqli->query("SELECT * FROM meal_kits WHERE meal_kit_id = $meal_kit_id");
if ($result && $result->num_rows > 0) {
    echo "Meal kit with ID $meal_kit_id exists\n";
    $row = $result->fetch_assoc();
    echo "Meal kit details: " . print_r($row, true);
} else {
    echo "Meal kit with ID $meal_kit_id does NOT exist\n";
}

// Test orders
$result = $mysqli->query("SELECT * FROM orders WHERE meal_kit_id = $meal_kit_id");
if ($result && $result->num_rows > 0) {
    echo "Orders found for meal kit $meal_kit_id: " . $result->num_rows . "\n";
} else {
    echo "No orders found for meal kit $meal_kit_id\n";
}

// Test order_items
$result = $mysqli->query("SELECT * FROM order_items WHERE meal_kit_id = $meal_kit_id");
if ($result && $result->num_rows > 0) {
    echo "Order items found for meal kit $meal_kit_id: " . $result->num_rows . "\n";
} else {
    echo "No order items found for meal kit $meal_kit_id\n";
}

// Test combined query
$result = $mysqli->query("
    SELECT COUNT(*) as order_count 
    FROM (
        SELECT meal_kit_id FROM orders WHERE meal_kit_id = $meal_kit_id
        UNION ALL
        SELECT meal_kit_id FROM order_items WHERE meal_kit_id = $meal_kit_id
    ) as combined_orders
");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Total orders found: " . $row['order_count'] . "\n";
} else {
    echo "Error executing combined query\n";
}

// Test prepared statement
$stmt = $mysqli->prepare("SELECT meal_kit_id FROM meal_kits WHERE meal_kit_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $meal_kit_id);
    if ($stmt->execute()) {
        echo "Prepared statement executed successfully\n";
    } else {
        echo "Error executing prepared statement: " . $stmt->error . "\n";
    }
} else {
    echo "Error preparing statement: " . $mysqli->error . "\n";
}
