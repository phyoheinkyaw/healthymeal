<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/connection.php';

// Fetch user data
$stmt = $mysqli->prepare("
    SELECT u.*, up.dietary_restrictions, up.allergies, up.cooking_experience, 
           up.household_size, up.calorie_goal 
    FROM users u 
    LEFT JOIN user_preferences up ON u.user_id = up.user_id 
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Healthy Meal Kit</title>
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
        padding-top: 56px;
        /* Added space for navbar */
    }

    .dashboard-container {
        display: flex;
        min-height: calc(100vh - 56px);
        /* Adjusted for navbar */
    }

    .sidebar {
        width: 250px;
        background: #343a40;
        color: #fff;
        position: fixed;
        height: calc(100% - 56px);
        /* Adjusted for navbar */
        top: 56px;
        /* Start below navbar */
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

    .card {
        border-radius: 10px;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 1.5rem;
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        padding: 1rem 1.25rem;
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
            top: 56px;
            /* Start below navbar */
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
        top: 70px;
        /* Adjusted for navbar */
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
                    <a href="profile.php" class="d-flex align-items-center active">
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
                    <a href="favorites.php" class="d-flex align-items-center">
                        <i class="bi bi-heart"></i>
                        <span>Favorites</span>
                    </a>
                </li>
                <li>
                    <a href="meal_plans.php" class="d-flex align-items-center">
                        <i class="bi bi-calendar-check"></i>
                        <span>Meal Plans</span>
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
                <h1>My Profile</h1>
                <p class="text-muted">Manage your account information and preferences</p>
            </div>

            <!-- Alert for messages -->
            <div id="profileMessage"></div>

            <div class="row">
                <!-- Personal Information -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <form id="profileForm" action="api/user/update_profile.php" method="POST">
                                <!-- Name Fields -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="firstName" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="firstName" name="firstName"
                                            value="<?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>"
                                            required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="lastName" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="lastName" name="lastName"
                                            value="<?php echo htmlspecialchars(explode(' ', $user['full_name'])[1]); ?>"
                                            required>
                                    </div>
                                </div>

                                <!-- Read-only Fields -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control"
                                            value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control"
                                            value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">Update Personal
                                        Information</button>
                                    <button type="button" class="btn btn-secondary"
                                        id="cancelProfileBtn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Summary -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Account Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <strong>Account Type:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <strong>Member Since:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php 
                                    // Assuming there's a created_at field, otherwise use a placeholder
                                    echo isset($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'N/A'; 
                                ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <strong>Email:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <strong>Dietary Preference:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php echo $user['dietary_restrictions'] ? ucfirst(str_replace('_', ' ', $user['dietary_restrictions'])) : 'None'; ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <strong>Cooking Level:</strong>
                                </div>
                                <div class="col-sm-6">
                                    <?php echo $user['cooking_experience'] ? ucfirst($user['cooking_experience']) : 'Not set'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dietary Preferences -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-cup-hot me-2"></i>Dietary Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form id="dietaryForm" action="api/user/update_profile.php" method="POST">
                                <div class="mb-3">
                                    <label for="dietary_restrictions" class="form-label">Dietary Restrictions</label>
                                    <select class="form-select" id="dietary_restrictions" name="dietary_restrictions">
                                        <option value="">None</option>
                                        <option value="vegetarian"
                                            <?php echo ($user['dietary_restrictions'] == 'vegetarian') ? 'selected' : ''; ?>>
                                            Vegetarian</option>
                                        <option value="vegan"
                                            <?php echo ($user['dietary_restrictions'] == 'vegan') ? 'selected' : ''; ?>>
                                            Vegan</option>
                                        <option value="gluten_free"
                                            <?php echo ($user['dietary_restrictions'] == 'gluten_free') ? 'selected' : ''; ?>>
                                            Gluten Free</option>
                                        <option value="dairy_free"
                                            <?php echo ($user['dietary_restrictions'] == 'dairy_free') ? 'selected' : ''; ?>>
                                            Dairy Free</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="allergies" class="form-label">Food Allergies</label>
                                    <input type="text" class="form-control" id="allergies" name="allergies"
                                        value="<?php echo htmlspecialchars($user['allergies'] ?? ''); ?>"
                                        placeholder="e.g., peanuts, shellfish">
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="cooking_experience" class="form-label">Cooking Experience</label>
                                        <select class="form-select" id="cooking_experience" name="cooking_experience">
                                            <option value="beginner"
                                                <?php echo ($user['cooking_experience'] == 'beginner') ? 'selected' : ''; ?>>
                                                Beginner</option>
                                            <option value="intermediate"
                                                <?php echo ($user['cooking_experience'] == 'intermediate') ? 'selected' : ''; ?>>
                                                Intermediate</option>
                                            <option value="advanced"
                                                <?php echo ($user['cooking_experience'] == 'advanced') ? 'selected' : ''; ?>>
                                                Advanced</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="household_size" class="form-label">Household Size</label>
                                        <input type="number" class="form-control" id="household_size"
                                            name="household_size"
                                            value="<?php echo htmlspecialchars($user['household_size'] ?? '1'); ?>"
                                            min="1" max="10">
                                    </div>
                                </div>

                                <!-- Calorie Goal -->
                                <div class="mb-3">
                                    <label for="calorie_goal" class="form-label">Daily Calorie Goal</label>
                                    <input type="number" class="form-control" id="calorie_goal" name="calorie_goal"
                                        value="<?php echo htmlspecialchars($user['calorie_goal'] ?? '2000'); ?>"
                                        min="1200" max="4000" step="50">
                                    <div class="form-text">Recommended daily calorie intake: 2000-2500 calories</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success flex-grow-1">Update Dietary
                                        Preferences</button>
                                    <button type="button" class="btn btn-secondary"
                                        id="cancelDietaryBtn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Security</h5>
                        </div>
                        <div class="card-body">
                            <form id="passwordForm" action="api/user/update_password.php" method="POST">
                                <div class="mb-3">
                                    <label for="currentPassword" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="currentPassword"
                                            name="currentPassword" required>
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="toggleCurrentPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="newPassword" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="newPassword" name="newPassword"
                                            pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                            required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Password must contain at least 8 characters, including uppercase, lowercase,
                                        number
                                        and special character.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirmNewPassword"
                                            name="confirmNewPassword" required>
                                        <button class="btn btn-outline-secondary" type="button"
                                            id="toggleConfirmNewPassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-warning flex-grow-1">Change Password</button>
                                    <button type="button" class="btn btn-secondary"
                                        id="cancelPasswordBtn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    // Toggle Sidebar
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.main-content').classList.toggle('active');
        document.querySelector('.overlay').classList.toggle('active');
    }

    // Store original form values for reset functionality
    let originalProfileValues = {};
    let originalDietaryValues = {};
    let originalPasswordValues = {};

    document.addEventListener('DOMContentLoaded', function() {
        // Store original form values when page loads
        saveOriginalFormValues();
        // Password toggle functionality
        function setupPasswordToggle(toggleButtonId, passwordFieldId) {
            const toggleButton = document.querySelector(toggleButtonId);
            const passwordField = document.querySelector(passwordFieldId);

            toggleButton.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.querySelector('i').classList.toggle('bi-eye');
                this.querySelector('i').classList.toggle('bi-eye-slash');
            });
        }

        // Setup password toggles
        setupPasswordToggle('#toggleCurrentPassword', '#currentPassword');
        setupPasswordToggle('#toggleNewPassword', '#newPassword');
        setupPasswordToggle('#toggleConfirmNewPassword', '#confirmNewPassword');

        // Profile form submission
        const profileForm = document.querySelector('#profileForm');
        const profileMessage = document.querySelector('#profileMessage');

        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Add first name and last name fields to form data
            const formData = new FormData(this);
            const fullName = formData.get('firstName') + ' ' + formData.get('lastName');
            formData.append('full_name', fullName);

            fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred. Please try again later.');
                });
        });

        // Dietary form submission
        const dietaryForm = document.querySelector('#dietaryForm');

        dietaryForm.addEventListener('submit', function(e) {
            e.preventDefault();

            fetch(this.action, {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred. Please try again later.');
                });
        });

        // Password form submission
        const passwordForm = document.querySelector('#passwordForm');

        passwordForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Check if passwords match
            const newPassword = document.querySelector('#newPassword').value;
            const confirmNewPassword = document.querySelector('#confirmNewPassword').value;

            if (newPassword !== confirmNewPassword) {
                showAlert('danger', 'New passwords do not match!');
                return;
            }

            fetch(this.action, {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        this.reset();
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred. Please try again later.');
                });
        });

        // Cancel button functionality
        document.querySelector('#cancelProfileBtn').addEventListener('click', function() {
            resetForm('profileForm', originalProfileValues);
            showAlert('info', 'Personal information changes cancelled.');
        });

        document.querySelector('#cancelDietaryBtn').addEventListener('click', function() {
            resetForm('dietaryForm', originalDietaryValues);
            showAlert('info', 'Dietary preference changes cancelled.');
        });

        document.querySelector('#cancelPasswordBtn').addEventListener('click', function() {
            document.querySelector('#passwordForm').reset();
            showAlert('info', 'Password change cancelled.');
        });

        // Function to save original form values
        function saveOriginalFormValues() {
            // Save profile form values
            const profileForm = document.querySelector('#profileForm');
            originalProfileValues = {
                firstName: profileForm.querySelector('#firstName').value,
                lastName: profileForm.querySelector('#lastName').value
            };

            // Save dietary form values
            const dietaryForm = document.querySelector('#dietaryForm');
            originalDietaryValues = {
                dietary_restrictions: dietaryForm.querySelector('#dietary_restrictions').value,
                allergies: dietaryForm.querySelector('#allergies').value,
                cooking_experience: dietaryForm.querySelector('#cooking_experience').value,
                household_size: dietaryForm.querySelector('#household_size').value,
                calorie_goal: dietaryForm.querySelector('#calorie_goal').value
            };
        }

        // Function to reset form to original values
        function resetForm(formId, originalValues) {
            const form = document.querySelector('#' + formId);

            // Reset each field to its original value
            for (const [field, value] of Object.entries(originalValues)) {
                if (form.querySelector('#' + field)) {
                    form.querySelector('#' + field).value = value;
                }
            }
        }

        // Function to show alerts
        function showAlert(type, message) {
            const alertDiv = document.getElementById('profileMessage');
            alertDiv.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = alertDiv.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
    });
    </script>

</body>

</html>