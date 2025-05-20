<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar bg-dark text-white" id="adminSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <a href="/hm/admin" class="text-white text-decoration-none">
                <i class="bi bi-gear-fill"></i>
                <span class="sidebar-brand-text">Admin Panel</span>
        </a>
        </div>
        <button type="button" class="btn-close-sidebar d-block d-lg-none" aria-label="Close sidebar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="/hm/admin" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/meal-kits.php" class="nav-link <?php echo $current_page === 'meal-kits.php' ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam"></i>
                        <span>Meal Kits</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/ingredients.php" class="nav-link <?php echo $current_page === 'ingredients.php' ? 'active' : ''; ?>">
                        <i class="bi bi-egg-fried"></i>
                        <span>Ingredients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/categories.php" class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                        <i class="bi bi-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/orders.php" class="nav-link <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                        <i class="bi bi-cart3"></i>
                        <span>Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/users.php" class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                        <i class="bi bi-people"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/health-tips.php" class="nav-link <?php echo $current_page === 'health-tips.php' ? 'active' : ''; ?>">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Health Tips</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/blog-posts.php" class="nav-link <?php echo $current_page === 'blog-posts.php' ? 'active' : ''; ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Blog Posts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/hm/admin/payment-settings.php" class="nav-link <?php echo $current_page === 'payment-settings.php' ? 'active' : ''; ?>">
                        <i class="bi bi-credit-card-2-front"></i>
                        <span>Payment Settings</span>
                    </a>
                </li>
            </ul>
        </nav>
        </div>
    
    <div class="sidebar-footer">
        <a href="/hm/admin/profile.php" class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-circle"></i>
            <span>Profile</span>
            </a>
        <a href="/hm" class="nav-link" target="_blank">
            <i class="bi bi-globe"></i>
            <span>View Website</span>
            </a>
        <a href="/hm/api/auth/logout.php" class="nav-link">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign out</span>
            </a>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile Header -->
<div class="mobile-header d-lg-none">
    <button type="button" class="btn-toggle-sidebar" id="toggleSidebarBtn">
        <i class="bi bi-list"></i>
    </button>
    <div class="mobile-brand">
        <span>Healthy Meal Kit</span>
    </div>
    <div class="mobile-actions">
        <a href="/hm/api/auth/logout.php" class="btn btn-link text-white p-0">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>