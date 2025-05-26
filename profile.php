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
                                    <?php echo ($user['role'] == 1) ? 'Admin' : 'User'; ?>
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
                                    <?php 
                                    $cooking_level = 'Not set';
                                    switch($user['cooking_experience']) {
                                        case 0:
                                            $cooking_level = 'Beginner';
                                            break;
                                        case 1:
                                            $cooking_level = 'Intermediate';
                                            break;
                                        case 2:
                                            $cooking_level = 'Advanced';
                                            break;
                                    }
                                    echo $cooking_level;
                                    ?>
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
                                            <option value="0"
                                                <?php echo ($user['cooking_experience'] == 0) ? 'selected' : ''; ?>>
                                                Beginner</option>
                                            <option value="1"
                                                <?php echo ($user['cooking_experience'] == 1) ? 'selected' : ''; ?>>
                                                Intermediate</option>
                                            <option value="2"
                                                <?php echo ($user['cooking_experience'] == 2) ? 'selected' : ''; ?>>
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

                <!-- Address Management -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>My Addresses <span class="address-counter ms-2 badge bg-secondary">0/6</span></h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addressModal" id="addAddressBtn">
                                <i class="bi bi-plus-circle"></i> Add New Address
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="addressesContainer" class="row g-3">
                                <!-- Addresses will be loaded here -->
                                <div class="col-12">
                                    <div class="text-center p-5 text-muted address-loading">
                                        <div class="spinner-border text-primary mb-3" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p>Loading saved addresses...</p>
                                    </div>
                                    <div class="text-center p-5 text-muted address-empty d-none">
                                        <i class="bi bi-geo-alt display-4"></i>
                                        <p class="mt-2">You don't have any saved addresses yet.</p>
                                        <button class="btn btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#addressModal">
                                            <i class="bi bi-plus-circle"></i> Add Your First Address
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Address Modal -->
    <div class="modal fade" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="addressForm">
                    <input type="hidden" id="addressId" name="address_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addressModalLabel">Add New Address</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addressName" class="form-label">Address Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addressName" name="address_name" placeholder="e.g. Home, Office, etc." required>
                        </div>
                        <div class="mb-3">
                            <label for="fullAddress" class="form-label">Street Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fullAddress" name="full_address" placeholder="123 Main St" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                            <div class="col-md-6">
                                <label for="postalCode" class="form-label">Postal Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="postalCode" name="postal_code" required>
                            </div>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="defaultAddress" name="is_default">
                            <label class="form-check-label" for="defaultAddress">Set as default address</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Address Confirmation Modal -->
    <div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this address?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
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
    let addresses = [];
    let currentAddressId = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Store original form values when page loads
        saveOriginalFormValues();
        
        // Load addresses
        loadAddresses();
        
        // Address form handling
        const addressForm = document.getElementById('addressForm');
        addressForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveAddress();
        });
        
        // Add address button click
        const addAddressBtn = document.getElementById('addAddressBtn');
        addAddressBtn.addEventListener('click', function(e) {
            const addressCounter = document.querySelector('.address-counter');
            const currentCount = parseInt(addressCounter.textContent.split('/')[0]);
            
            if (currentCount >= 6) {
                e.preventDefault();
                e.stopPropagation();
                showAlert('danger', 'You have reached the maximum limit of 6 addresses. Please delete an existing address before adding a new one.');
                return false;
            }
        });
        
        // Reset address form when modal is hidden
        const addressModal = document.getElementById('addressModal');
        addressModal.addEventListener('hidden.bs.modal', function() {
            addressForm.reset();
            document.getElementById('addressId').value = '';
            document.getElementById('addressModalLabel').textContent = 'Add New Address';
            // Ensure default checkbox is unchecked
            document.getElementById('defaultAddress').checked = false;
        });
        
        // Also ensure default checkbox starts unchecked when modal opens
        document.getElementById('addAddressBtn').addEventListener('click', function() {
            document.getElementById('defaultAddress').checked = false;
        });
        
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
            formData.append('form_type', 'profile');

            fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        // Save new values as original values
                        saveOriginalFormValues();
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
            
            const formData = new FormData(this);
            formData.append('form_type', 'dietary');
            
            // Add default values for first name and last name to ensure they're present
            // These are required by the update_profile.php endpoint
            if (!formData.has('firstName') || !formData.has('lastName')) {
                const profileForm = document.querySelector('#profileForm');
                formData.append('firstName', profileForm.querySelector('#firstName').value);
                formData.append('lastName', profileForm.querySelector('#lastName').value);
            }

            fetch(this.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        // Save new values as original values to prevent reset issues
                        saveOriginalFormValues();
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

            // Check password strength
            const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!passwordPattern.test(newPassword)) {
                showAlert('danger', 'Password must contain at least 8 characters, including uppercase, lowercase, number, and special character.');
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
        
        // Setup confirm delete button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (currentAddressId) {
                deleteAddress(currentAddressId);
            }
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
                const fieldElement = form.querySelector('#' + field);
                if (fieldElement) {
                    // Handle different input types appropriately
                    if (fieldElement.tagName === 'SELECT') {
                        fieldElement.value = value;
                    } else {
                        fieldElement.value = value;
                    }
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
        
        // Address Management Functions
        function loadAddresses() {
            fetch('api/user/get_addresses.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('addressesContainer');
                    
                    // Remove loading indicator if it exists
                    const loadingIndicator = document.querySelector('.address-loading');
                    if (loadingIndicator) {
                        loadingIndicator.remove();
                    }
                    
                    // Clear existing content
                    container.innerHTML = '';
                    
                    if (data.success && data.addresses && data.addresses.length > 0) {
                        addresses = data.addresses;
                        
                        // Add addresses
                        addresses.forEach(address => {
                            container.appendChild(createAddressCard(address));
                        });
                        
                        // Make sure empty state is hidden
                        const emptyState = document.querySelector('.address-empty');
                        if (emptyState) {
                            emptyState.classList.add('d-none');
                        }
                        
                        // Update address counter
                        const addressCounter = document.querySelector('.address-counter');
                        addressCounter.textContent = `${addresses.length}/6`;
                        
                        // Disable add button if limit reached
                        const addAddressBtn = document.getElementById('addAddressBtn');
                        if (addresses.length >= 6) {
                            addAddressBtn.classList.add('disabled');
                            addAddressBtn.setAttribute('disabled', 'disabled');
                        } else {
                            addAddressBtn.classList.remove('disabled');
                            addAddressBtn.removeAttribute('disabled');
                        }
                    } else {
                        // Show empty state
                        const emptyStateHtml = `
                            <div class="col-12">
                                <div class="text-center p-5 text-muted address-empty">
                                    <i class="bi bi-geo-alt display-4"></i>
                                    <p class="mt-2">You don't have any saved addresses yet.</p>
                                    <button class="btn btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#addressModal">
                                        <i class="bi bi-plus-circle"></i> Add Your First Address
                                    </button>
                                </div>
                            </div>
                        `;
                        container.innerHTML = emptyStateHtml;
                        
                        // Update address counter for empty state
                        const addressCounter = document.querySelector('.address-counter');
                        addressCounter.textContent = '0/6';
                        
                        // Enable add button
                        const addAddressBtn = document.getElementById('addAddressBtn');
                        addAddressBtn.classList.remove('disabled');
                        addAddressBtn.removeAttribute('disabled');
                    }
                })
                .catch(error => {
                    console.error('Error loading addresses:', error);
                    showAlert('danger', 'Failed to load addresses. Please try again later.');
                    
                    // Show error state
                    const container = document.getElementById('addressesContainer');
                    container.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-danger">
                                Failed to load addresses. Please try again later.
                            </div>
                        </div>
                    `;
                    
                    // Update address counter for error state
                    const addressCounter = document.querySelector('.address-counter');
                    addressCounter.textContent = '0/6';
                });
        }
        
        function createAddressCard(address) {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 mb-3';
            
            const card = document.createElement('div');
            card.className = 'card h-100 position-relative';
            if (address.is_default == 1) {
                card.classList.add('border-primary');
            }
            
            // Default badge if this is the default address
            if (address.is_default == 1) {
                const badge = document.createElement('div');
                badge.className = 'position-absolute top-0 end-0 badge bg-primary rounded-pill m-2';
                badge.textContent = 'Default';
                card.appendChild(badge);
            }
            
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';
            
            const title = document.createElement('h5');
            title.className = 'card-title';
            title.textContent = address.address_name;
            cardBody.appendChild(title);
            
            const addressText = document.createElement('p');
            addressText.className = 'card-text';
            addressText.textContent = address.full_address;
            cardBody.appendChild(addressText);
            
            const cityZip = document.createElement('p');
            cityZip.className = 'card-text text-muted';
            cityZip.textContent = `${address.city}, ${address.postal_code}`;
            cardBody.appendChild(cityZip);
            
            const cardFooter = document.createElement('div');
            cardFooter.className = 'card-footer d-flex justify-content-between bg-transparent';
            
            // Edit button
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-sm btn-outline-primary';
            editBtn.innerHTML = '<i class="bi bi-pencil"></i> Edit';
            editBtn.addEventListener('click', () => editAddress(address));
            cardFooter.appendChild(editBtn);
            
            // Delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-sm btn-outline-danger';
            deleteBtn.innerHTML = '<i class="bi bi-trash"></i> Delete';
            deleteBtn.addEventListener('click', () => showDeleteConfirmation(address.address_id));
            cardFooter.appendChild(deleteBtn);
            
            // Set as default button (only show if not default)
            if (address.is_default != 1) {
                const defaultBtn = document.createElement('button');
                defaultBtn.className = 'btn btn-sm btn-outline-secondary';
                defaultBtn.innerHTML = '<i class="bi bi-star"></i> Set as Default';
                defaultBtn.addEventListener('click', () => setAsDefault(address.address_id));
                cardFooter.appendChild(defaultBtn);
            }
            
            card.appendChild(cardBody);
            card.appendChild(cardFooter);
            col.appendChild(card);
            
            return col;
        }
        
        function editAddress(address) {
            // Populate form with address data
            document.getElementById('addressId').value = address.address_id;
            document.getElementById('addressName').value = address.address_name;
            document.getElementById('fullAddress').value = address.full_address;
            document.getElementById('city').value = address.city;
            document.getElementById('postalCode').value = address.postal_code;
            document.getElementById('defaultAddress').checked = address.is_default == 1;
            
            // Update modal title
            document.getElementById('addressModalLabel').textContent = 'Edit Address';
            
            // Show modal
            const addressModal = new bootstrap.Modal(document.getElementById('addressModal'));
            addressModal.show();
        }
        
        function saveAddress() {
            const form = document.getElementById('addressForm');
            const formData = new FormData(form);
            
            // Get the address ID if editing
            const addressId = formData.get('address_id');
            const isEditing = addressId && addressId !== '';
            
            // Check if we're at the limit for addresses
            if (!isEditing) {
                const addressCounter = document.querySelector('.address-counter');
                const currentCount = parseInt(addressCounter.textContent.split('/')[0]);
                
                if (currentCount >= 6) {
                    showAlert('danger', 'You have reached the maximum limit of 6 addresses. Please delete an existing address before adding a new one.');
                    return;
                }
            }
            
            // Convert checkbox to boolean - ensure it's explicitly true only when checked
            const isDefaultChecked = document.getElementById('defaultAddress').checked;
            formData.set('is_default', isDefaultChecked);
            
            // Convert FormData to JSON
            const addressData = {};
            formData.forEach((value, key) => {
                // Special handling for is_default to ensure it's sent as boolean
                if (key === 'is_default') {
                    addressData[key] = value === 'true' || value === true;
                } else {
                    addressData[key] = value;
                }
            });
            
            console.log('Address data being sent:', addressData);
            
            // Only include address_id if we're editing
            if (!isEditing) {
                delete addressData.address_id;
            }
            
            // If we're editing, ensure we're using the API correctly
            const endpoint = isEditing ? 'api/user/update_address.php' : 'api/user/save_address.php';
            
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(addressData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide modal
                    const addressModal = bootstrap.Modal.getInstance(document.getElementById('addressModal'));
                    addressModal.hide();
                    
                    // Show success message
                    showAlert('success', data.message || 'Address saved successfully!');
                    
                    // Reload addresses
                    loadAddresses();
                } else {
                    showAlert('danger', data.message || 'Failed to save address.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while saving your address. Please try again.');
            });
        }
        
        function showDeleteConfirmation(addressId) {
            currentAddressId = addressId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteAddressModal'));
            deleteModal.show();
        }
        
        function deleteAddress(addressId) {
            fetch('api/user/delete_address.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ address_id: addressId })
            })
            .then(response => response.json())
            .then(data => {
                // Hide modal
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteAddressModal'));
                deleteModal.hide();
                
                if (data.success) {
                    // Show success message
                    showAlert('success', 'Address deleted successfully!');
                    
                    // Reload addresses
                    loadAddresses();
                } else {
                    showAlert('danger', data.message || 'Failed to delete address.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while deleting the address. Please try again.');
            });
        }
        
        function setAsDefault(addressId) {
            fetch('api/user/set_default_address.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ address_id: addressId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showAlert('success', 'Default address updated successfully!');
                    
                    // Reload addresses
                    loadAddresses();
                } else {
                    showAlert('danger', data.message || 'Failed to update default address.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while updating your default address. Please try again.');
            });
        }
    });
    </script>

</body>

</html>