<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/connection.php';
require_once 'api/auth/utils/auth_functions.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// If user is already logged in, redirect based on role
if ($role) {
    $redirect_url = ($role == 1) ? 'admin' : 'index.php';
    header("Location: " . $redirect_url);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Healthy Meal Kit</title>
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
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card auth-card">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Welcome Back</h2>
                    
                    <!-- Alert for errors/messages -->
                    <div id="loginMessage">
                        <?php if (isset($_SESSION['reactivation_success']) && $_SESSION['reactivation_success'] === true): ?>
                            <div class="alert alert-success">
                                Your account has been successfully reactivated. You can now log in.
                            </div>
                            <?php unset($_SESSION['reactivation_success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['login_error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                    switch($_SESSION['login_error']) {
                                        case 'invalid_password':
                                            echo 'Invalid password. Please try again.';
                                            break;
                                        case 'user_not_found':
                                            echo 'Username not found. Please check your username or register.';
                                            break;
                                        default:
                                            echo 'An error occurred. Please try again.';
                                    }
                                    unset($_SESSION['login_error']);
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form id="loginForm" action="api/auth/login.php" method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="true">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary">Register</a></p>
                        <a href="forgot-password.php" class="text-muted">Forgot Password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.querySelector('i').classList.toggle('bi-eye');
        this.querySelector('i').classList.toggle('bi-eye-slash');
    });

    // Handle form submission with AJAX
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                    // Show success message
                    const messageDiv = document.getElementById('loginMessage');
                    messageDiv.innerHTML = `
                    <div class="alert alert-success">
                        ${data.message}
                    </div>
                `;
                    
                    // Redirect after a short delay
                    setTimeout(function() {
                    window.location.href = data.redirect_url;
                }, 1000);
            } else {
                    // Handle inactive account
                    if (data.message === 'account_inactive') {
                        window.location.href = data.redirect_url;
                    } else {
                        // Show error message
                        const messageDiv = document.getElementById('loginMessage');
                        messageDiv.innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message}
                    </div>
                `;
                    }
            }
        })
        .catch(error => {
                console.error('Error:', error);
                const messageDiv = document.getElementById('loginMessage');
                messageDiv.innerHTML = `
                <div class="alert alert-danger">
                    An error occurred. Please try again.
                </div>
            `;
            });
        });
    }
});
</script>

</body>
</html> 