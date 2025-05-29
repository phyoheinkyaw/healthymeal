<?php
/**
 * Script to check for inactive users
 * This can be run as a cron job once per day, or triggered via web requests
 * 
 * Example cron entry (daily at midnight):
 * 0 0 * * * php /path/to/check_inactive_users.php
 */

// Flag to check if script is being called directly or included
$called_from_web = (isset($suppress_output) && $suppress_output === true);

// Set to run without time limit
set_time_limit(0);

// Load database configuration if not already loaded
if (!isset($mysqli)) {
    require_once dirname(__DIR__) . '/config/connection.php';
}

// Log function
function logMessage($message, $called_from_web = false) {
    if ($called_from_web) {
        // If called from web, log to file instead of outputting
        $log_file = dirname(__DIR__) . '/logs/inactive_users.log';
        $log_dir = dirname($log_file);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents(
            $log_file, 
            date('[Y-m-d H:i:s]') . " $message\n", 
            FILE_APPEND
        );
    } else {
        echo date('[Y-m-d H:i:s]') . " $message\n";
    }
}

// Check if the script should run today
function shouldRunToday($mysqli) {
    // Check if we have a settings table with last_inactive_check value
    $result = $mysqli->query("SHOW TABLES LIKE 'system_settings'");
    
    if ($result->num_rows == 0) {
        // Table doesn't exist, create it
        $mysqli->query("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(255) PRIMARY KEY,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }
    
    // Check when script was last run
    $checkResult = $mysqli->query("
        SELECT setting_value FROM system_settings 
        WHERE setting_key = 'last_inactive_check'
    ");
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $lastRun = $checkResult->fetch_assoc()['setting_value'];
        // Only run if last run date is not today
        return date('Y-m-d') !== date('Y-m-d', strtotime($lastRun));
    }
    
    // Never run before or error occurred, so run it
    return true;
}

// Update the last run timestamp
function updateLastRun($mysqli) {
    $stmt = $mysqli->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES ('last_inactive_check', NOW())
        ON DUPLICATE KEY UPDATE setting_value = NOW()
    ");
    $stmt->execute();
    $stmt->close();
}

// Main execution function
function checkInactiveUsers($mysqli, $called_from_web = false) {
    logMessage("Starting inactive users check...", $called_from_web);
    
    // Find users who haven't logged in for more than 3 months (90 days) and are still active
    $sql = "SELECT user_id, username, email, last_login_at 
            FROM users 
            WHERE is_active = 1 
            AND last_login_at IS NOT NULL 
            AND last_login_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    
    $result = $mysqli->query($sql);
    
    if ($result === false) {
        logMessage("Error executing query: " . $mysqli->error, $called_from_web);
        return;
    }
    
    $count = $result->num_rows;
    logMessage("Found $count users to deactivate.", $called_from_web);
    
    if ($count > 0) {
        // Prepare update statement
        $updateSql = "UPDATE users 
                     SET is_active = 0,
                         inactivity_reason = 'No login activity for over 3 months',
                         deactivated_at = NOW()
                     WHERE user_id = ?";
        
        $updateStmt = $mysqli->prepare($updateSql);
        
        if ($updateStmt === false) {
            logMessage("Error preparing statement: " . $mysqli->error, $called_from_web);
            return;
        }
        
        $updateStmt->bind_param("i", $userId);
        
        // Process each inactive user
        while ($user = $result->fetch_assoc()) {
            $userId = $user['user_id'];
            $lastLogin = $user['last_login_at'];
            
            // Execute update
            if ($updateStmt->execute()) {
                logMessage("Deactivated user: {$user['username']} (ID: $userId) - Last login: $lastLogin", $called_from_web);
            } else {
                logMessage("Failed to deactivate user ID $userId: " . $updateStmt->error, $called_from_web);
            }
        }
        
        $updateStmt->close();
    } else {
        logMessage("No inactive users found.", $called_from_web);
    }
    
    // Also check for users who have never logged in (created more than 3 months ago)
    $neverLoggedInSql = "SELECT user_id, username, email, created_at 
                         FROM users 
                         WHERE is_active = 1 
                         AND last_login_at IS NULL 
                         AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    
    $neverResult = $mysqli->query($neverLoggedInSql);
    
    if ($neverResult === false) {
        logMessage("Error executing never logged in query: " . $mysqli->error, $called_from_web);
        return;
    }
    
    $neverCount = $neverResult->num_rows;
    logMessage("Found $neverCount users who have never logged in to deactivate.", $called_from_web);
    
    if ($neverCount > 0) {
        // Prepare update statement
        $neverUpdateSql = "UPDATE users 
                          SET is_active = 0,
                              inactivity_reason = 'Account created but never used for over 3 months',
                              deactivated_at = NOW()
                          WHERE user_id = ?";
        
        $neverUpdateStmt = $mysqli->prepare($neverUpdateSql);
        
        if ($neverUpdateStmt === false) {
            logMessage("Error preparing statement: " . $mysqli->error, $called_from_web);
            return;
        }
        
        $neverUpdateStmt->bind_param("i", $neverUserId);
        
        // Process each inactive user
        while ($neverUser = $neverResult->fetch_assoc()) {
            $neverUserId = $neverUser['user_id'];
            $createdAt = $neverUser['created_at'];
            
            // Execute update
            if ($neverUpdateStmt->execute()) {
                logMessage("Deactivated user: {$neverUser['username']} (ID: $neverUserId) - Created but never logged in since: $createdAt", $called_from_web);
            } else {
                logMessage("Failed to deactivate user ID $neverUserId: " . $neverUpdateStmt->error, $called_from_web);
            }
        }
        
        $neverUpdateStmt->close();
    }
    
    logMessage("Inactive user check completed.", $called_from_web);
    
    // Update the last run timestamp
    updateLastRun($mysqli);
}

// If the script is called directly (not included in another file)
if (!$called_from_web) {
    checkInactiveUsers($mysqli, false);
    $mysqli->close();
} 