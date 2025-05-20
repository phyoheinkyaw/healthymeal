<?php
session_start();
require_once 'config/connection.php';

// Check if inactive user ID is set in session
if (!isset($_SESSION['inactive_user_id'])) {
    // Redirect to login page if no inactive user
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['inactive_user_id'];

// Get user data
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // User not found, redirect to login
    unset($_SESSION['inactive_user_id']);
    header('Location: login.php');
    exit;
}

$user = $result->fetch_assoc();
$inactiveReason = $user['inactivity_reason'];
$deactivatedAt = new DateTime($user['deactivated_at']);
$formattedDeactivationDate = $deactivatedAt->format('F j, Y');

// Handle reactivation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate'])) {
    // Update user to active
    $updateSql = "UPDATE users SET 
                  is_active = 1, 
                  last_login_at = NOW(),
                  reactivated_at = NOW(),
                  inactivity_reason = NULL 
                  WHERE user_id = ?";
    
    $updateStmt = $mysqli->prepare($updateSql);
    $updateStmt->bind_param("i", $userId);
    
    if ($updateStmt->execute()) {
        // Clear inactive user from session
        unset($_SESSION['inactive_user_id']);
        
        // Set success message
        $_SESSION['reactivation_success'] = true;
        
        // Redirect to login
        header('Location: login.php');
        exit;
    } else {
        $error = "Failed to reactivate account. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Reactivation - Healthy Meal Kit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h3 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Account Inactive</h3>
                    </div>
                    <div class="card-body">
                        <h4>Hello, <?= htmlspecialchars($user['full_name']) ?></h4>
                        
                        <div class="alert alert-warning">
                            <p><strong>Your account has been deactivated due to inactivity.</strong></p>
                            <p><strong>Reason:</strong> <?= htmlspecialchars($inactiveReason ?? 'No activity for over 3 months') ?></p>
                            <p><strong>Deactivated on:</strong> <?= $formattedDeactivationDate ?></p>
                        </div>
                        
                        <p>Your account has been inactive for an extended period. For security reasons, we've temporarily deactivated it.</p>
                        
                        <p>Would you like to reactivate your account now?</p>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="d-grid gap-2">
                                <button type="submit" name="reactivate" class="btn btn-primary btn-lg">
                                    <i class="bi bi-arrow-clockwise me-2"></i> Reactivate My Account
                                </button>
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i> Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 