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

// Get and sanitize input
$firstName = htmlspecialchars(trim($_POST['firstName']));
$lastName = htmlspecialchars(trim($_POST['lastName']));
$dietaryRestrictions = htmlspecialchars(trim($_POST['dietary_restrictions']));
$allergies = htmlspecialchars(trim($_POST['allergies']));

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

$householdSize = filter_var($_POST['household_size'], FILTER_VALIDATE_INT);
$calorieGoal = filter_var($_POST['calorie_goal'], FILTER_VALIDATE_INT);

// Validate inputs
if (empty($firstName) || empty($lastName)) {
    echo json_encode(['success' => false, 'message' => 'Name fields cannot be empty']);
    exit;
}

if ($householdSize < 1 || $householdSize > 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid household size']);
    exit;
}

if ($calorieGoal < 1200 || $calorieGoal > 4000) {
    echo json_encode(['success' => false, 'message' => 'Invalid calorie goal']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // Update full name in users table
    $fullName = $firstName . ' ' . $lastName;
    $stmt = $mysqli->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("si", $fullName, $_SESSION['user_id']);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update name');
    }

    // Update preferences
    $stmt = $mysqli->prepare("
        UPDATE user_preferences 
        SET dietary_restrictions = ?,
            allergies = ?,
            cooking_experience = ?,
            household_size = ?,
            calorie_goal = ?
        WHERE user_id = ?
    ");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("sssiii", 
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

    // If no rows were affected, insert new preferences
    if ($stmt->affected_rows === 0) {
        $stmt = $mysqli->prepare("
            INSERT INTO user_preferences 
            (user_id, dietary_restrictions, allergies, cooking_experience, household_size, calorie_goal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isssii", 
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

    $mysqli->commit();
    
    // Update session
    $_SESSION['full_name'] = $fullName;
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
    
} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Profile Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile. Please try again.'
    ]);
}

$mysqli->close(); 