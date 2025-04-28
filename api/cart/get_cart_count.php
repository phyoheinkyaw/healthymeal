<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$count = $row['total_items'] ?? 0;
echo json_encode(['success' => true, 'count' => (int)$count]);
?>
