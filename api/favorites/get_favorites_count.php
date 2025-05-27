<?php
session_start();
require_once '../../config/connection.php';

// Default response
$response = [
    'success' => false,
    'count' => 0
];

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get favorites count
    $query = "SELECT COUNT(*) as count FROM user_favorites WHERE user_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        $response['success'] = true;
        $response['count'] = (int)$data['count'];
    }
    
    $stmt->close();
}

// Set headers for JSON response
header('Content-Type: application/json');
echo json_encode($response); 