<?php
session_start();
require_once '../../config/connection.php';

header('Content-Type: application/json');

// This is a placeholder response since we've migrated from shipping methods to delivery options
// This prevents errors in case any old code still tries to call this endpoint
try {
    // Return a placeholder shipping method until all references are updated
    echo json_encode([
        'success' => true,
        'message' => 'This endpoint is deprecated. Please use delivery options instead.',
        'shipping_methods' => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 