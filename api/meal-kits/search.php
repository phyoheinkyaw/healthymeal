<?php
require_once '../../config/connection.php';

header('Content-Type: application/json');

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit;
}

try {
    // Prepare the search query without reviews
    $sql = "SELECT 
                mk.meal_kit_id,
                mk.name,
                mk.description,
                (mk.preparation_price + IFNULL(SUM(i.price_per_100g * mki.default_quantity / 100), 0)) as price,
                mk.image_url
            FROM meal_kits mk
            LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
            LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
            WHERE (mk.name LIKE ? OR mk.description LIKE ?) AND mk.is_active = 1
            GROUP BY mk.meal_kit_id
            ORDER BY RAND()
            LIMIT ?";
    
    $searchTerm = "%$query%";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssi", $searchTerm, $searchTerm, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $meals = [];
    while ($row = $result->fetch_assoc()) {
        $meals[] = [
            'id' => $row['meal_kit_id'],
            'name' => $row['name'],
            'description' => substr($row['description'], 0, 100) . '...',
            'price' => $row['price'],
            'image_url' => $row['image_url']
        ];
    }
    
    $total_count = $mysqli->query("SELECT COUNT(*) as count 
                                  FROM meal_kits 
                                  WHERE (name LIKE '%$query%' 
                                  OR description LIKE '%$query%') AND is_active = 1")->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => $meals,
        'total_count' => $total_count,
        'has_more' => $total_count > count($meals)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching'
    ]);
}