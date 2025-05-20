<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Handle user reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_user'])) {
    $userId = $_POST['user_id'] ?? 0;
    
    if ($userId > 0) {
        $updateSql = "UPDATE users SET 
                      is_active = 1, 
                      reactivated_at = NOW(),
                      inactivity_reason = NULL 
                      WHERE user_id = ?";
        
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $userId);
        
        if ($updateStmt->execute()) {
            $successMessage = "User ID #$userId has been successfully reactivated.";
        } else {
            $errorMessage = "Failed to reactivate user: " . $conn->error;
        }
        
        $updateStmt->close();
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';

// Build SQL based on filter
$sql = "SELECT 
            user_id, 
            username, 
            email, 
            full_name, 
            role, 
            is_active, 
            created_at, 
            last_login_at,
            deactivated_at,
            reactivated_at,
            inactivity_reason
        FROM users";

if ($filter === 'inactive') {
    $sql .= " WHERE is_active = 0";
} elseif ($filter === 'active') {
    $sql .= " WHERE is_active = 1";
}

$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);

// Count users by status
$countSql = "SELECT 
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_count,
                COUNT(*) as total_count
            FROM users";
$countResult = $conn->query($countSql);
$counts = $countResult->fetch_assoc();

// Page title based on filter
$pageTitle = 'All Users';
if ($filter === 'inactive') {
    $pageTitle = 'Inactive Users';
} elseif ($filter === 'active') {
    $pageTitle = 'Active Users';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Healthy Meal Kit Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-badge {
            width: 85px;
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h1><i class="bi bi-people-fill"></i> User Management</h1>
                <p class="text-muted">Manage user accounts and activity status</p>
            </div>
            <div class="col-md-6 text-end">
                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $successMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $errorMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h3 class="mb-0"><?= $counts['total_count'] ?? 0 ?></h3>
                                        <p class="text-muted mb-0">Total Users</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h3 class="mb-0 text-success"><?= $counts['active_count'] ?? 0 ?></h3>
                                        <p class="text-muted mb-0">Active Users</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h3 class="mb-0 text-danger"><?= $counts['inactive_count'] ?? 0 ?></h3>
                                        <p class="text-muted mb-0">Inactive Users</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?= $pageTitle ?></h5>
                <div class="btn-group" role="group">
                    <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                        All Users
                    </a>
                    <a href="?filter=active" class="btn btn-outline-success <?= $filter === 'active' ? 'active' : '' ?>">
                        Active Users
                    </a>
                    <a href="?filter=inactive" class="btn btn-outline-danger <?= $filter === 'inactive' ? 'active' : '' ?>">
                        Inactive Users
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Last Login</th>
                                <th>Inactive Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($user = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $user['user_id'] ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <?php if ($user['role'] == 1): ?>
                                                <span class="badge bg-primary">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active'] == 1): ?>
                                                <span class="badge bg-success status-badge">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger status-badge">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <?php if ($user['last_login_at']): ?>
                                                <?= date('Y-m-d H:i', strtotime($user['last_login_at'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['inactivity_reason']): ?>
                                                <span class="text-danger"><?= htmlspecialchars($user['inactivity_reason']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active'] == 0): ?>
                                                <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to reactivate this user?');">
                                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                    <button type="submit" name="reactivate_user" class="btn btn-sm btn-success">
                                                        <i class="bi bi-arrow-clockwise"></i> Reactivate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="user_details.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 