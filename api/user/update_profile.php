<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form type
$formType = isset($_POST['form_type']) ? $_POST['form_type'] : 'profile';

// Get and sanitize input
$firstName = isset($_POST['firstName']) ? htmlspecialchars(trim($_POST['firstName'])) : '';
$lastName = isset($_POST['lastName']) ? htmlspecialchars(trim($_POST['lastName'])) : '';
$dietaryRestrictions = isset($_POST['dietary_restrictions']) ? htmlspecialchars(trim($_POST['dietary_restrictions'])) : '';
$allergies = isset($_POST['allergies']) ? htmlspecialchars(trim($_POST['allergies'])) : '';

// Convert cooking experience to numeric value
$cookingExperience = 0; // Default to beginner
if (isset($_POST['cooking_experience'])) {
    if ($_POST['cooking_experience'] === 'intermediate') {
        $cookingExperience = 1;
    } elseif ($_POST['cooking_experience'] === 'advanced') {
        $cookingExperience = 2;
    } elseif (is_numeric($_POST['cooking_experience'])) {
        $cookingExperience = intval($_POST['cooking_experience']);
        // Ensure value is within valid range
        if ($cookingExperience < 0 || $cookingExperience > 2) {
            $cookingExperience = 0; // Reset to beginner if out of range
        }
    }
}

$householdSize = isset($_POST['household_size']) ? filter_var($_POST['household_size'], FILTER_VALIDATE_INT) : 1;
$calorieGoal = isset($_POST['calorie_goal']) ? filter_var($_POST['calorie_goal'], FILTER_VALIDATE_INT) : 2000;

// Validate inputs based on form type
if ($formType === 'profile') {
    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['success' => false, 'message' => 'Name fields cannot be empty']);
        exit;
    }
}

// Validate household size and calorie goal if present
if ($formType === 'dietary') {
    if ($householdSize < 1 || $householdSize > 10) {
        echo json_encode(['success' => false, 'message' => 'Invalid household size']);
        exit;
    }

    if ($calorieGoal < 1200 || $calorieGoal > 4000) {
        echo json_encode(['success' => false, 'message' => 'Invalid calorie goal']);
        exit;
    }
}

try {
    $mysqli->begin_transaction();
    
    // Update full name in users table if this is a profile form submission
    if ($formType === 'profile') {
        $fullName = $firstName . ' ' . $lastName;
        $stmt = $mysqli->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $mysqli->error);
        }
        
        $stmt->bind_param("si", $fullName, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update name');
        }
        
        // Update session
        $_SESSION['full_name'] = $fullName;
    }

    // Update preferences if this is a dietary form submission
    if ($formType === 'dietary') {
        $stmt = $mysqli->prepare("
            SELECT COUNT(*) as count FROM user_preferences WHERE user_id = ?
        ");
        
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            // Update existing preferences
            $stmt = $mysqli->prepare("
                UPDATE user_preferences 
                SET dietary_restrictions = ?,
                    allergies = ?,
                    cooking_experience = ?,
                    household_size = ?,
                    calorie_goal = ?
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("ssiiii", 
                $dietaryRestrictions,
                $allergies,
                $cookingExperience,
                $householdSize,
                $calorieGoal,
                $_SESSION['user_id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update preferences');
            }
        } else {
            // Insert new preferences
            $stmt = $mysqli->prepare("
                INSERT INTO user_preferences 
                (user_id, dietary_restrictions, allergies, cooking_experience, household_size, calorie_goal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("issiii", 
                $_SESSION['user_id'],
                $dietaryRestrictions,
                $allergies,
                $cookingExperience,
                $householdSize,
                $calorieGoal
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create preferences');
            }
        }
    }

    $mysqli->commit();
    
    echo json_encode([
        'success' => true,
        'message' => ($formType === 'profile' ? 'Profile' : 'Dietary preferences') . ' updated successfully'
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Profile Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update ' . ($formType === 'profile' ? 'profile' : 'dietary preferences') . '. Please try again.'
    ]);
}

$mysqli->close(); 