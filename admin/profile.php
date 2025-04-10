<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Get admin user data
$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("
    SELECT u.*, up.dietary_restrictions, up.allergies, up.cooking_experience, up.household_size
    FROM users u
    LEFT JOIN user_preferences up ON u.user_id = up.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<div class="overlay" onclick="toggleSidebar()"></div>
<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="sidebar-toggle">
        <button class="btn btn-dark" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <main class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="page-title">My Profile</h3>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <form id="profileForm">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dietary Restrictions</label>
                                    <select class="form-select" name="dietary_restrictions">
                                        <option value="">None</option>
                                        <option value="vegetarian" <?php echo ($user['dietary_restrictions'] == 'vegetarian') ? 'selected' : ''; ?>>Vegetarian</option>
                                        <option value="vegan" <?php echo ($user['dietary_restrictions'] == 'vegan') ? 'selected' : ''; ?>>Vegan</option>
                                        <option value="halal" <?php echo ($user['dietary_restrictions'] == 'halal') ? 'selected' : ''; ?>>Halal</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Allergies</label>
                                    <textarea class="form-control" name="allergies" rows="2"><?php echo htmlspecialchars($user['allergies'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Cooking Experience</label>
                                    <select class="form-select" name="cooking_experience">
                                        <option value="beginner" <?php echo ($user['cooking_experience'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                        <option value="intermediate" <?php echo ($user['cooking_experience'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                        <option value="advanced" <?php echo ($user['cooking_experience'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Household Size</label>
                                    <input type="number" class="form-control" name="household_size" value="<?php echo htmlspecialchars($user['household_size'] ?? 1); ?>" min="1">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="updateProfile()">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Change Password</h5>
                            <form id="passwordForm">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="button" class="btn btn-warning" onclick="updatePassword()">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>
<script src="assets/js/profile.js"></script>

</body>
</html> 