<?php
/**
 * Script to check for inactive users
 * This should be run as a cron job once per day
 * 
 * Example cron entry (daily at midnight):
 * 0 0 * * * php /path/to/check_inactive_users.php
 */

// Set to run without time limit
set_time_limit(0);

// Load database configuration
require_once dirname(__DIR__) . '/config/connection.php';

// Log function
function logMessage($message) {
    echo date('[Y-m-d H:i:s]') . " $message\n";
}

logMessage("Starting inactive users check...");

// Find users who haven't logged in for more than 3 months (90 days) and are still active
$sql = "SELECT user_id, username, email, last_login_at 
        FROM users 
        WHERE is_active = 1 
        AND last_login_at IS NOT NULL 
        AND last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";

$result = $mysqli->query($sql);

if ($result === false) {
    logMessage("Error executing query: " . $mysqli->error);
    exit(1);
}

$count = $result->num_rows;
logMessage("Found $count users to deactivate.");

if ($count > 0) {
    // Prepare update statement
    $updateSql = "UPDATE users 
                 SET is_active = 0,
                     inactivity_reason = 'No login activity for over 3 months',
                     deactivated_at = NOW()
                 WHERE user_id = ?";
    
    $updateStmt = $mysqli->prepare($updateSql);
    
    if ($updateStmt === false) {
        logMessage("Error preparing statement: " . $mysqli->error);
        exit(1);
    }
    
    $updateStmt->bind_param("i", $userId);
    
    // Process each inactive user
    while ($user = $result->fetch_assoc()) {
        $userId = $user['user_id'];
        $lastLogin = $user['last_login_at'];
        
        // Execute update
        if ($updateStmt->execute()) {
            logMessage("Deactivated user: {$user['username']} (ID: $userId) - Last login: $lastLogin");
        } else {
            logMessage("Failed to deactivate user ID $userId: " . $updateStmt->error);
        }
    }
    
    $updateStmt->close();
} else {
    logMessage("No inactive users found.");
}

// Also check for users who have never logged in (created more than 3 months ago)
$neverLoggedInSql = "SELECT user_id, username, email, created_at 
                     FROM users 
                     WHERE is_active = 1 
                     AND last_login_at IS NULL 
                     AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";

$neverResult = $mysqli->query($neverLoggedInSql);

if ($neverResult === false) {
    logMessage("Error executing never logged in query: " . $mysqli->error);
    exit(1);
}

$neverCount = $neverResult->num_rows;
logMessage("Found $neverCount users who have never logged in to deactivate.");

if ($neverCount > 0) {
    // Prepare update statement
    $neverUpdateSql = "UPDATE users 
                      SET is_active = 0,
                          inactivity_reason = 'Account created but never used for over 3 months',
                          deactivated_at = NOW()
                      WHERE user_id = ?";
    
    $neverUpdateStmt = $mysqli->prepare($neverUpdateSql);
    
    if ($neverUpdateStmt === false) {
        logMessage("Error preparing statement: " . $mysqli->error);
        exit(1);
    }
    
    $neverUpdateStmt->bind_param("i", $neverUserId);
    
    // Process each inactive user
    while ($neverUser = $neverResult->fetch_assoc()) {
        $neverUserId = $neverUser['user_id'];
        $createdAt = $neverUser['created_at'];
        
        // Execute update
        if ($neverUpdateStmt->execute()) {
            logMessage("Deactivated user: {$neverUser['username']} (ID: $neverUserId) - Created but never logged in since: $createdAt");
        } else {
            logMessage("Failed to deactivate user ID $neverUserId: " . $neverUpdateStmt->error);
        }
    }
    
    $neverUpdateStmt->close();
}

$mysqli->close();
logMessage("Inactive user check completed.");
?> 