<?php
require_once '../../../includes/auth_check.php';

header('Content-Type: application/json');

// Check for admin role
$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['meal_kit_image']) || $_FILES['meal_kit_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false, 
        'message' => 'No file uploaded or upload error occurred'
    ]);
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = '../../../uploads/meal-kits/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get file info
$file = $_FILES['meal_kit_image'];
$file_name = $file['name'];
$file_tmp = $file['tmp_name'];
$file_size = $file['size'];
$file_error = $file['error'];

// Get file extension
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Allowed file extensions
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// Validate file extension
if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions)
    ]);
    exit();
}

// Validate file size (max 5MB)
if ($file_size > 5242880) {
    echo json_encode([
        'success' => false, 
        'message' => 'File is too large. Maximum size is 5MB.'
    ]);
    exit();
}

// Generate a unique filename
$new_file_name = uniqid('meal_kit_') . '.' . $file_ext;
$upload_path = $upload_dir . $new_file_name;

// Move uploaded file to destination
if (move_uploaded_file($file_tmp, $upload_path)) {
    // Return success with the relative URL to the image
    $relative_url = '/uploads/meal-kits/' . $new_file_name;
    echo json_encode([
        'success' => true, 
        'message' => 'Image uploaded successfully',
        'image_url' => $relative_url
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to upload image'
    ]);
}