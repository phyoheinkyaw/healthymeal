<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Fetch all users except current admin
$users = [];
$current_user_id = $_SESSION['user_id'];
$result = $mysqli->query("
    SELECT 
        u.user_id,
        u.username,
        u.email,
        u.full_name,
        u.role,
        u.created_at,
        COALESCE(up.dietary_restrictions, 'None') as dietary_restrictions,
        COALESCE(up.allergies, 'None') as allergies,
        COALESCE(up.cooking_experience, 'Not specified') as cooking_experience,
        COALESCE(up.household_size, 0) as household_size
    FROM users u
    LEFT JOIN user_preferences up ON u.user_id = up.user_id
    WHERE u.user_id != $current_user_id
    ORDER BY u.created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
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
        <!-- ALERT CONTAINER FOR JS ALERTS -->
        <div id="alertsContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 2000; min-width: 350px; max-width: 400px;"></div>
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <h3 class="page-title">User Management</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-lg"></i> Add New User
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Joined Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>#<?php echo $user['user_id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px; border-radius: 50%; font-size: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                        </div>
                                                        <div class="ms-2">
                                                            <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                            <div class="small text-muted">@<?php echo htmlspecialchars($user['username']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                onclick="viewUserDetails(<?php echo $user['user_id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Save User</button>
            </div>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="userDetails"></div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" name="user_id">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current password">
                        <div class="form-text">Only fill this if you want to change the password</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dietary Restrictions</label>
                        <input type="text" class="form-control" name="dietary_restrictions">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allergies</label>
                        <input type="text" class="form-control" name="allergies">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cooking Experience</label>
                        <select class="form-select" name="cooking_experience">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                            <option value="Not specified">Not specified</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Household Size</label>
                        <input type="number" class="form-control" name="household_size" min="0">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateUser()">Update User</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>
<script src="assets/js/users.js"></script>

</body>
</html>