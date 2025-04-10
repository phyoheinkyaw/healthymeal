<?php
session_start();
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate parameters
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing meal kit ID']);
    exit();
}

$meal_kit_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$meal_kit_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid meal kit ID']);
    exit();
}

// Get current status
$result = $mysqli->query("SELECT is_active FROM meal_kits WHERE meal_kit_id = $meal_kit_id");
if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Meal kit not found']);
    exit();
}

$current_status = $result->fetch_assoc()['is_active'];
$new_status = !$current_status; // Toggle the current status

// Convert boolean to integer (0 or 1) for MySQL
$new_status_int = $new_status ? 1 : 0;

// Start transaction
$mysqli->begin_transaction();

try {
    // Update meal kit status
    $stmt = $mysqli->prepare("UPDATE meal_kits SET is_active = ? WHERE meal_kit_id = ?");
    $stmt->bind_param("ii", $new_status_int, $meal_kit_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update meal kit status: " . $mysqli->error);
    }

    // Store success message in session
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'message' => 'Status updated successfully'
    ];

    // Commit transaction
    $mysqli->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'is_active' => $new_status
    ]);

} catch (Exception $e) {
    // Store error message in session
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => $e->getMessage()
    ];
    
    // Rollback transaction on error
    $mysqli->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
