<?php
session_start();
require_once 'config/connection.php';

// Helper function to get image URL from DB value (copied from meal-kits.php)
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0];
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$total_count = $mysqli->query("SELECT COUNT(*) as count 
                              FROM meal_kits 
                              WHERE name LIKE '%$query%' 
                              OR description LIKE '%$query%'")->fetch_assoc()['count'];

$total_pages = ceil($total_count / $per_page);

// Get meal kits for current page without reviews
$stmt = $mysqli->prepare("
    SELECT 
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
    ORDER BY name ASC
    LIMIT ? OFFSET ?
");

$searchTerm = "%$query%";
$stmt->bind_param("ssii", $searchTerm, $searchTerm, $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1>Search Results</h1>
                <p class="text-muted">
                    Found <?php echo $total_count; ?> results for "<?php echo htmlspecialchars($query); ?>"
                </p>
            </div>
        </div>

        <?php if ($total_count > 0): ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php while ($meal = $result->fetch_assoc()): ?>
            <div class="col">
                <div class="card h-100">
                    <?php $img_url = get_meal_kit_image_url($meal['image_url'], $meal['name']); ?>
                    <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top" alt="Meal Kit Image">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($meal['name']); ?></h5>
                        <p class="card-text">
                            <?php echo htmlspecialchars(substr($meal['description'], 0, 100)) . '...'; ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="price-tag">
                                <strong>$<?php echo number_format($meal['price'], 2); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <a href="meal-details.php?id=<?php echo $meal['meal_kit_id']; ?>"
                            class="btn btn-outline-primary w-100">View Details</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="row mt-4">
            <div class="col">
                <nav aria-label="Search results pages">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="alert alert-info">
            No meal kits found matching your search criteria.
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>

</html>