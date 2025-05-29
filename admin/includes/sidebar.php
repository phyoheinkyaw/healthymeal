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
        
        <div class="sidebar-actions">
            <button type="button" class="btn-close-sidebar d-block d-lg-none" aria-label="Close sidebar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
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

<!-- Floating Notification Button -->
<div class="notification-floating d-none d-lg-block">
    <button class="notification-button" id="notificationBell">
        <i class="bi bi-bell-fill"></i>
        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
    </button>
    
    <!-- Desktop Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <div>Notifications</div>
            <div class="close-notifications" id="closeNotifications"><i class="bi bi-x"></i></div>
        </div>
        
        <div class="notification-tabs">
            <div class="notification-tab active" data-target="pendingOrdersSection">Orders</div>
            <div class="notification-tab" data-target="pendingPaymentsSection">Payments</div>
        </div>
        
        <div class="notification-content">
            <div id="pendingOrdersSection" class="notification-section active">
                <div id="pendingOrdersContainer">
                    <div class="no-notifications">
                        <i class="bi bi-hourglass text-muted mb-2" style="font-size: 2rem;"></i>
                        <p>Loading pending orders...</p>
                    </div>
                </div>
            </div>
            
            <div id="pendingPaymentsSection" class="notification-section">
                <div id="pendingPaymentsContainer">
                    <div class="no-notifications">
                        <i class="bi bi-hourglass text-muted mb-2" style="font-size: 2rem;"></i>
                        <p>Loading pending payments...</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="notification-footer">
            <a href="/hm/admin/orders.php?filter=pending">View All Pending Items</a>
        </div>
    </div>
</div>

<!-- Mobile Floating Notification Button -->
<div class="notification-floating d-block d-lg-none">
    <button class="notification-button" id="mobileBell">
        <i class="bi bi-bell-fill"></i>
        <span class="notification-badge" id="mobileNotificationBadge" style="display: none;">0</span>
    </button>
</div>

<!-- Mobile Notifications Dropdown -->
<div class="notification-dropdown" id="mobileNotificationDropdown" style="display: none;">
    <div class="notification-header">
        <div>Notifications</div>
        <div class="close-notifications" id="closeMobileNotifications"><i class="bi bi-x"></i></div>
    </div>
    
    <div class="notification-tabs">
        <div class="notification-tab active" data-target="mobilePendingOrdersSection">Orders</div>
        <div class="notification-tab" data-target="mobilePendingPaymentsSection">Payments</div>
    </div>
    
    <div class="notification-content">
        <div id="mobilePendingOrdersSection" class="notification-section active">
            <div id="mobilePendingOrdersContainer">
                <div class="no-notifications">
                    <i class="bi bi-hourglass text-muted mb-2" style="font-size: 2rem;"></i>
                    <p>Loading pending orders...</p>
                </div>
            </div>
        </div>
        
        <div id="mobilePendingPaymentsSection" class="notification-section">
            <div id="mobilePendingPaymentsContainer">
                <div class="no-notifications">
                    <i class="bi bi-hourglass text-muted mb-2" style="font-size: 2rem;"></i>
                    <p>Loading pending payments...</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="notification-footer">
        <a href="/hm/admin/orders.php?filter=pending">View All Pending Items</a>
    </div>
</div>

<!-- Link notification CSS and JS -->
<link rel="stylesheet" href="/hm/admin/assets/css/notifications.css">
<script src="/hm/admin/assets/js/notifications.js" defer></script>