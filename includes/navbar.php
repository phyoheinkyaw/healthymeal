<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Search Styles -->
<style>
.search-results-wrapper {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    margin-top: 5px;
}

.live-search-results {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-height: 400px;
    overflow-y: auto;
    display: none;
}

.live-search-results.show {
    display: block;
}

.search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid #dee2e6;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.2s ease;
    text-decoration: none;
    color: #2F2E41;
    background: white;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-item:hover {
    background-color: #FFF3E6;
    text-decoration: none;
}

.search-result-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.search-result-info {
    flex: 1;
    min-width: 0;
    padding-right: 8px;
}

.search-result-name {
    font-weight: 600;
    color: #2F2E41;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.search-result-price {
    color: #FF6B35;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 4px;
}

.search-result-description {
    color: #6c757d;
    font-size: 0.875rem;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

.view-all-results {
    padding: 12px 16px;
    text-align: center;
    background-color: #FFF3E6;
    color: #FF6B35;
    font-weight: 600;
    cursor: pointer;
    border-top: 1px solid #dee2e6;
    text-decoration: none;
    display: block;
}

.view-all-results:hover {
    background-color: #FFE8D9;
    text-decoration: none;
}

/* Notification bell styles */
#notificationBell {
    position: relative;
}

#notificationBell i {
    font-size: 1.2rem;
    transition: color 0.3s ease;
}

#notificationBell .badge {
    position: absolute;
    top: 0;
    right: 0;
    font-size: 0.65rem;
    padding: 0.25rem 0.4rem;
    border-radius: 50%;
    transform: translate(25%, -25%);
}

#notificationBell i.text-danger {
    animation: bellPulse 2s infinite;
}

@keyframes bellPulse {
    0% {
        transform: scale(1);
    }
    15% {
        transform: scale(1.25) rotate(15deg);
    }
    30% {
        transform: scale(1) rotate(-10deg);
    }
    45% {
        transform: scale(1.15) rotate(5deg);
    }
    60% {
        transform: scale(1);
    }
    100% {
        transform: scale(1);
    }
}

@media (max-width: 768px) {
    .search-results-wrapper {
        position: fixed;
        top: 60px;
        left: 0;
        right: 0;
        margin: 0 15px;
    }

    .live-search-results {
        max-height: calc(100vh - 120px);
        margin: 0;
        border-radius: 0 0 8px 8px;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="index.php">Healthy Meal Kit</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'meal-kits.php' ? 'active' : ''; ?>" href="meal-kits.php">Meal Kits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'nutrition.php' ? 'active' : ''; ?>" href="nutrition.php">Nutrition</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'blog.php' ? 'active' : ''; ?>" href="blog.php">Blog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'about.php' ? 'active' : ''; ?>" href="about.php">About</a>
                </li>
            </ul>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Search Form -->
            <form class="d-flex position-relative me-3" role="search">
                <div class="input-group">
                    <input type="text" class="form-control" id="navbarSearch" placeholder="Search meal kits..." 
                           aria-label="Search meal kits">
                    <button class="btn btn-outline-success" type="button" id="navbarSearchBtn">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <div class="search-results-wrapper position-absolute w-100">
                    <div class="live-search-results" id="liveSearchResults">
                        <!-- Results will be populated dynamically -->
                    </div>
                </div>
            </form>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><a class="dropdown-item" href="favorites.php">Favorites <span class="badge bg-primary" id="favoritesCount">0</span></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="api/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link" href="orders.php" id="notificationBell">
                            <i class="bi bi-bell"></i>
                            <span class="badge bg-danger" id="notificationCount" style="display:none;">0</span>
                        </a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link" href="cart.php">
                            <i class="bi bi-cart3"></i>
                            <span class="badge bg-primary" id="cartCount">0</span>
                        </a>
                    </li>
                </ul>
            <?php else: ?>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch cart count
    fetch('/hm/api/cart/get_cart_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartBadge = document.getElementById('cartCount');
                cartBadge.textContent = data.count;
                // Always show the badge
                cartBadge.style.display = 'inline-block';
            }
        });
        
    // Fetch favorites count
    fetch('/hm/api/favorites/get_favorites_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const favoritesBadge = document.getElementById('favoritesCount');
                favoritesBadge.textContent = data.count;
                // Only show the badge if there are favorites
                favoritesBadge.style.display = data.count > 0 ? 'inline-block' : 'none';
            }
        });
        
    // Fetch unread notifications count
    fetchNotificationCount();
    
    // Set up auto-refresh for notifications every 60 seconds
    setInterval(fetchNotificationCount, 60000);
});

// Function to fetch notification count
function fetchNotificationCount() {
    fetch('/hm/api/notifications/get_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationBadge = document.getElementById('notificationCount');
                notificationBadge.textContent = data.count;
                // Only show the badge if there are unread notifications
                notificationBadge.style.display = data.count > 0 ? 'inline-block' : 'none';
                
                // If there are notifications, add a pulse animation to the bell
                if (data.count > 0) {
                    const bellIcon = document.querySelector('#notificationBell i');
                    bellIcon.classList.add('text-danger');
                } else {
                    const bellIcon = document.querySelector('#notificationBell i');
                    if (bellIcon) {
                        bellIcon.classList.remove('text-danger');
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error fetching notification count:', error);
        });
}
</script>
