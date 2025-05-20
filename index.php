<?php
require_once 'config/connection.php';
require_once 'includes/auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for remember me token and get user role
$role = false;
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
} else {
    $role = checkRememberToken();
}

// Fetch featured meal kits
$featured_query = "SELECT mk.*, c.name as category_name,
           SUM(i.calories_per_100g * mki.default_quantity / 100) as base_calories,
           SUM(i.price_per_100g * mki.default_quantity / 100) as ingredients_price
    FROM meal_kits mk
    LEFT JOIN categories c ON mk.category_id = c.category_id
    LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
    LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    WHERE mk.is_active = 1
    GROUP BY mk.meal_kit_id ORDER BY RAND() LIMIT 3";
$featured_result = $mysqli->query($featured_query);

// Fetch latest blog posts
$blog_query = "SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 3";
$blog_result = $mysqli->query($blog_query);

// Fetch random health tip
$tip_query = "SELECT * FROM health_tips ORDER BY RAND() LIMIT 1";
$tip_result = $mysqli->query($tip_query);
$health_tip = $tip_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Healthy Meal Kit - Fresh & Nutritious Meals Delivered</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h1 class="mb-4">Healthy Eating Made Simple</h1>
                    <p class="lead mb-4">Discover personalized meal kits that match your calorie goals and dietary
                        preferences. Fresh ingredients, delicious recipes, delivered to your door.</p>
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                        <a href="meal-kits.php" class="btn btn-light btn-lg px-5">View Menu</a>
                        <?php 
                        if(!isset($_SESSION['user_id'])) {
                            echo '<a href="register.php" class="btn btn-outline-light btn-lg px-5">Join Now</a>';
                        } else if($_SESSION['role'] == 1) {
                            echo '<a href="admin/index.php" class="btn btn-outline-light btn-lg px-5">Admin Dashboard</a>';
                        } else {
                            echo '<a href="nutrition.php" class="btn btn-outline-light btn-lg px-5">View Nutrition Information</a>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Meal Kits -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Featured Meal Kits</h2>
            <div class="row">
                <?php while($meal = $featured_result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=<?php echo urlencode($meal['name']); ?>"
                            class="card-img-top" alt="<?php echo htmlspecialchars($meal['name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title text-primary-custom"><?php echo htmlspecialchars($meal['name']); ?>
                            </h5>
                            <p class="card-text"><?php echo htmlspecialchars($meal['description']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span
                                    class="h5 mb-0"><?php echo number_format($meal['preparation_price']+$meal['ingredients_price'], 0); ?> MMK</span>
                                <a href="meal-details.php?id=<?php echo $meal['meal_kit_id']; ?>"
                                    class="btn btn-primary">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Health Tip Section -->
    <section class="health-tip-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 mx-auto text-center">
                    <h3 class="mb-4 text-primary-custom">Daily Health Tip</h3>
                    <p class="lead mb-4"><?php echo htmlspecialchars($health_tip['content']); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Blog Posts -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Latest from Our Blog</h2>
            <div class="row">
                <?php while($post = $blog_result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card blog-card">
                        <div class="card-body">
                            <h5 class="card-title text-primary-custom"><?php echo htmlspecialchars($post['title']); ?>
                            </h5>
                            <p class="card-text">
                                <?php echo substr(htmlspecialchars($post['content']), 0, 150) . '...'; ?></p>
                            <a href="blog-post.php?id=<?php echo $post['post_id']; ?>"
                                class="btn btn-outline-primary">Read More</a>
                        </div>
                        <div class="card-footer text-muted">
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

</body>

</html>
<?php
// Close the database connection
$mysqli->close();
?>