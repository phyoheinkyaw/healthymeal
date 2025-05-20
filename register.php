<?php
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Healthy Meal Kit</title>
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
        <div class="col-md-8 col-lg-6">
            <div class="card auth-card">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Create Account</h2>
                    
                    <!-- Alert for errors/messages -->
                    <div id="registerMessage"></div>
                    
                    <form id="registerForm" action="api/auth/register.php" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastName" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$" 
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <div class="password-strength-meter">
                                    <div class="progress">
                                        <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div id="password-feedback" class="form-text text-muted mt-1">
                                    Password must meet these requirements:
                                </div>
                                <ul id="password-requirements" class="list-unstyled small mt-1">
                                    <li id="req-length"><i class="bi bi-x-circle text-danger"></i> At least 8 characters</li>
                                    <li id="req-lowercase"><i class="bi bi-x-circle text-danger"></i> At least one lowercase letter</li>
                                    <li id="req-uppercase"><i class="bi bi-x-circle text-danger"></i> At least one uppercase letter</li>
                                    <li id="req-number"><i class="bi bi-x-circle text-danger"></i> At least one number</li>
                                    <li id="req-special"><i class="bi bi-x-circle text-danger"></i> At least one special character</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a>
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-primary">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
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

    // Setup password toggles for both password fields
    setupPasswordToggle('#togglePassword', '#password');
    setupPasswordToggle('#toggleConfirmPassword', '#confirmPassword');

    // Password strength checker
    const passwordField = document.querySelector('#password');
    const strengthBar = document.querySelector('#password-strength-bar');
    const reqLength = document.querySelector('#req-length');
    const reqLowercase = document.querySelector('#req-lowercase');
    const reqUppercase = document.querySelector('#req-uppercase');
    const reqNumber = document.querySelector('#req-number');
    const reqSpecial = document.querySelector('#req-special');
    
    function checkPasswordStrength(password) {
        // Initialize requirements check
        let meetsLength = password.length >= 8;
        let meetsLowercase = /[a-z]/.test(password);
        let meetsUppercase = /[A-Z]/.test(password);
        let meetsNumber = /[0-9]/.test(password);
        let meetsSpecial = /[^A-Za-z0-9]/.test(password);
        
        // Update requirements list with check or x mark
        updateRequirement(reqLength, meetsLength);
        updateRequirement(reqLowercase, meetsLowercase);
        updateRequirement(reqUppercase, meetsUppercase);
        updateRequirement(reqNumber, meetsNumber);
        updateRequirement(reqSpecial, meetsSpecial);
        
        // Calculate strength percentage
        let strength = 0;
        if (password.length > 0) {
            if (meetsLength) strength += 20;
            if (meetsLowercase) strength += 20;
            if (meetsUppercase) strength += 20;
            if (meetsNumber) strength += 20;
            if (meetsSpecial) strength += 20;
        }
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        // Set color based on strength
        if (strength < 40) {
            strengthBar.className = 'progress-bar bg-danger';
        } else if (strength < 80) {
            strengthBar.className = 'progress-bar bg-warning';
        } else {
            strengthBar.className = 'progress-bar bg-success';
        }
        
        return strength === 100;
    }
    
    function updateRequirement(element, isMet) {
        if (isMet) {
            element.querySelector('i').className = 'bi bi-check-circle text-success';
        } else {
            element.querySelector('i').className = 'bi bi-x-circle text-danger';
        }
    }
    
    passwordField.addEventListener('input', function() {
        checkPasswordStrength(this.value);
    });

    // Form validation
    const registerForm = document.querySelector('#registerForm');
    const registerMessage = document.querySelector('#registerMessage');
    const confirmPasswordField = document.querySelector('#confirmPassword');
    
    // Check password match when confirm password changes
    confirmPasswordField.addEventListener('input', function() {
        const password = passwordField.value;
        const confirmPassword = this.value;
        
        if (password === confirmPassword) {
            this.setCustomValidity('');
        } else {
            this.setCustomValidity('Passwords do not match');
        }
    });
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Check if password meets strength requirements
        if (!checkPasswordStrength(passwordField.value)) {
            registerMessage.innerHTML = '<div class="alert alert-danger">Password does not meet all requirements</div>';
            return;
        }
        
        // Check if passwords match
        const password = passwordField.value;
        const confirmPassword = confirmPasswordField.value;
        
        if (password !== confirmPassword) {
            registerMessage.innerHTML = '<div class="alert alert-danger">Passwords do not match!</div>';
            return;
        }
        
        fetch(this.action, {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                registerMessage.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                registerMessage.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            registerMessage.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
        });
    });
});
</script>

</body>
</html> 