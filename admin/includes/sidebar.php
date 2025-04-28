<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar bg-dark text-white" id="adminSidebar">
    <div class="d-flex flex-column flex-shrink-0 p-3 h-100">
        <a href="/hm/admin" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-gear-fill me-2"></i>
            <span class="fs-4">Admin Panel</span>
        </a>
        <hr>
        <div class="flex-grow-1">
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="/hm/admin" class="nav-link text-white <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/meal-kits.php" class="nav-link text-white <?php echo $current_page === 'meal-kits.php' ? 'active' : ''; ?>">
                        <i class="bi bi-box-seam me-2"></i>
                        Meal Kits
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/ingredients.php" class="nav-link text-white <?php echo $current_page === 'ingredients.php' ? 'active' : ''; ?>">
                        <i class="bi bi-egg-fried me-2"></i>
                        Ingredients
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/categories.php" class="nav-link text-white <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                        <i class="bi bi-tags me-2"></i>
                        Categories
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/orders.php" class="nav-link text-white <?php echo $current_page === 'orders.php' ? 'active' : ''; ?>">
                        <i class="bi bi-cart3 me-2"></i>
                        Orders
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/users.php" class="nav-link text-white <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                        <i class="bi bi-people me-2"></i>
                        Users
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/health-tips.php" class="nav-link text-white <?php echo $current_page === 'health-tips.php' ? 'active' : ''; ?>">
                        <i class="bi bi-heart-pulse me-2"></i>
                        Health Tips
                    </a>
                </li>
                <li>
                    <a href="/hm/admin/payment-settings.php" class="nav-link text-white <?php echo $current_page === 'payment-settings.php' ? 'active' : ''; ?>">
                        <i class="bi bi-credit-card-2-front me-2"></i>
                        Payment Settings
                    </a>
                </li>
            </ul>
        </div>
        <hr>
        <div class="d-flex flex-column">
            <a href="/hm/admin/profile.php" class="nav-link text-white d-flex align-items-center p-2 <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-circle me-2"></i>
                <span class="d-none d-sm-inline">Profile</span>
            </a>
            <a href="/hm" class="nav-link text-white d-flex align-items-center p-2" target="_blank">
                <i class="bi bi-globe me-2"></i>
                <span class="d-none d-sm-inline">View Website</span>
            </a>
            <a href="/hm/api/auth/logout.php" class="nav-link text-white d-flex align-items-center p-2">
                <i class="bi bi-box-arrow-right me-2"></i>
                <span class="d-none d-sm-inline">Sign out</span>
            </a>
        </div>
    </div>
</div>

<!-- Overlay for mobile view -->
<div class="overlay"></div>