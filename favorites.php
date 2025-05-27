<?php
require_once 'includes/auth_check.php';

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Helper function to get image URL from DB value (same as in meal-kits.php)
function get_meal_kit_image_url($image_url_db, $meal_kit_name) {
    if (!$image_url_db) return 'https://placehold.co/600x400/FFF3E6/FF6B35?text=' . urlencode($meal_kit_name);
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    // Get the base URL up to the project root (e.g. /hm or /yourproject)
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0]; // e.g. '/hm'
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

// Handle add/remove favorite action
if (isset($_POST['action']) && isset($_POST['meal_kit_id'])) {
    $meal_kit_id = $_POST['meal_kit_id'];
    
    if ($_POST['action'] === 'add') {
        // Add to favorites
        $insert_query = "INSERT IGNORE INTO user_favorites (user_id, meal_kit_id) VALUES (?, ?)";
        $stmt = $mysqli->prepare($insert_query);
        $stmt->bind_param("ii", $user_id, $meal_kit_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($_POST['action'] === 'remove') {
        // Remove from favorites
        $delete_query = "DELETE FROM user_favorites WHERE user_id = ? AND meal_kit_id = ?";
        $stmt = $mysqli->prepare($delete_query);
        $stmt->bind_param("ii", $user_id, $meal_kit_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // If AJAX request, return success response
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Otherwise redirect back to the same page to prevent form resubmission
    header('Location: favorites.php');
    exit;
}

// Fetch user's favorite meal kits
$favorites_query = "
    SELECT m.*, c.name as category_name 
    FROM meal_kits m 
    JOIN user_favorites f ON m.meal_kit_id = f.meal_kit_id 
    LEFT JOIN categories c ON m.category_id = c.category_id
    WHERE f.user_id = ? 
    ORDER BY f.created_at DESC
";

$stmt = $mysqli->prepare($favorites_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$favorites = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user data for sidebar
$userStmt = $mysqli->prepare("SELECT username, full_name FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Dashboard specific styles */
        body {
            background-color: #f8f9fa;
            padding-top: 56px; /* Added space for navbar */
        }
        
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 56px); /* Adjusted for navbar */
        }
        
        .sidebar {
            width: 250px;
            background: #343a40;
            color: #fff;
            position: fixed;
            height: calc(100% - 56px); /* Adjusted for navbar */
            top: 56px; /* Start below navbar */
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 999;
        }
        
        .sidebar .sidebar-header {
            padding: 20px;
            background: #2c3136;
        }
        
        .sidebar ul li a {
            padding: 15px 20px;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: #2c3136;
            border-left-color: #198754;
        }
        
        .sidebar ul li a i {
            margin-right: 10px;
        }
        
        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .content-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                width: 100%;
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 250px;
            }
            
            .overlay {
                display: none;
                position: fixed;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.7);
                z-index: 998;
                opacity: 0;
                transition: all 0.5s ease-in-out;
                top: 56px; /* Start below navbar */
            }
            
            .overlay.active {
                display: block;
                opacity: 1;
            }
        }
        
        .toggle-btn {
            background: #198754;
            color: white;
            position: fixed;
            top: 70px; /* Adjusted for navbar */
            left: 15px;
            z-index: 999;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            display: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        @media (max-width: 768px) {
            .toggle-btn {
                display: block;
            }
        }
        
        .favorite-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .favorite-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .favorite-card .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        
        .card-footer {
            background-color: transparent;
            border-top: none;
        }
        
        .favorite-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .favorite-badge:hover {
            transform: scale(1.1);
        }
        
        .empty-favorites {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-favorites i {
            font-size: 64px;
            color: var(--primary-light);
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="overlay" onclick="toggleSidebar()"></div>

    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>My Account</h3>
                <p class="mb-0"><?php echo htmlspecialchars($user['username']); ?></p>
            </div>
            
            <ul class="list-unstyled">
                <li>
                    <a href="index.php" class="d-flex align-items-center">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="d-flex align-items-center">
                        <i class="bi bi-person"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li>
                    <a href="orders.php" class="d-flex align-items-center">
                        <i class="bi bi-bag"></i>
                        <span>My Orders</span>
                    </a>
                </li>
                <li>
                    <a href="favorites.php" class="d-flex align-items-center active">
                        <i class="bi bi-heart"></i>
                        <span>Favorites</span>
                    </a>
                </li>
                <li>
                    <a href="api/auth/logout.php" class="d-flex align-items-center">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">My Favorites</h1>
                        </div>
                        <div class="col-sm-6">
                            <div class="float-sm-end">
                                <a href="meal-kits.php" class="btn btn-primary">
                                    <i class="bi bi-grid"></i> Browse Meal Kits
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Favorites Content -->
            <div class="container-fluid">
                <?php if (empty($favorites)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-favorites">
                            <i class="bi bi-heart"></i>
                            <h3>No Favorites Yet</h3>
                            <p class="text-muted">You haven't added any meal kits to your favorites yet.</p>
                            <a href="meal-kits.php" class="btn btn-primary mt-3">Browse Meal Kits</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($favorites as $meal_kit): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card favorite-card">
                            <div class="position-relative">
                                <?php $img_url = get_meal_kit_image_url($meal_kit['image_url'], $meal_kit['name']); ?>
                                <img src="<?php echo htmlspecialchars($img_url); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($meal_kit['name']); ?>">
                                
                                <div class="favorite-badge remove-favorite" data-meal-kit-id="<?php echo $meal_kit['meal_kit_id']; ?>">
                                    <i class="bi bi-heart-fill"></i>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($meal_kit['category_name']); ?></span>
                                <h5 class="card-title"><?php echo htmlspecialchars($meal_kit['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo substr(htmlspecialchars($meal_kit['description']), 0, 100) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="price-tag">
                                        <span class="fw-bold text-primary-custom">K<?php echo number_format($meal_kit['preparation_price']); ?></span>
                                    </div>
                                    <div class="nutrition-info">
                                        <small class="text-muted"><?php echo $meal_kit['base_calories']; ?> calories</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <a href="meal-details.php?id=<?php echo $meal_kit['meal_kit_id']; ?>" class="btn btn-outline-primary">View Details</a>
                                    <!-- <button class="btn btn-primary add-to-cart" data-meal-kit-id="<?php echo $meal_kit['meal_kit_id']; ?>">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button> -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/toast-notifications.php'; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Toggle Sidebar
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.main-content').classList.toggle('active');
        document.querySelector('.overlay').classList.toggle('active');
    }
    
    $(document).ready(function() {
        // Remove from favorites
        $('.remove-favorite').on('click', function() {
            const mealKitId = $(this).data('meal-kit-id');
            const card = $(this).closest('.col-md-6');
            
            $.ajax({
                url: 'favorites.php',
                type: 'POST',
                data: {
                    action: 'remove',
                    meal_kit_id: mealKitId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Animate removal
                        card.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if there are any favorites left
                            if ($('.favorite-card').length === 0) {
                                location.reload(); // Reload to show empty state
                            }
                            
                            // Update favorites count in navbar
                            updateFavoritesCount();
                        });
                    }
                }
            });
        });
        
        // Function to update favorites count in navbar
        function updateFavoritesCount() {
            fetch('api/favorites/get_favorites_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const favoritesBadge = document.getElementById('favoritesCount');
                        if (favoritesBadge) {
                            favoritesBadge.textContent = data.count;
                            favoritesBadge.style.display = data.count > 0 ? 'inline-block' : 'none';
                        }
                    }
                });
        }
    });
    </script>
</body>

</html> 