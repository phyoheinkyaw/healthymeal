<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Add new tip
        if ($action === 'add' && !isset($_POST['tip_id'])) {
            $content = isset($_POST['content']) ? $mysqli->real_escape_string($_POST['content']) : '';
            
            // Validate content length
            if (strlen($content) < 10) {
                $error = "Health tip must be at least 10 characters long";
                goto display_form;
            }
            
            $stmt = $mysqli->prepare("INSERT INTO health_tips (content) VALUES (?)");
            $stmt->bind_param("s", $content);
            
            if ($stmt->execute()) {
                $success = "Health tip added successfully";
                $stmt->close();
                goto display_form;
            } else {
                $error = "Error adding health tip: " . $mysqli->error;
                $stmt->close();
                goto display_form;
            }
        }
        
        // Edit tip
        if ($action === 'edit' && isset($_POST['tip_id'])) {
            $tip_id = (int)$_POST['tip_id'];
            $content = isset($_POST['content']) ? $mysqli->real_escape_string($_POST['content']) : '';
            
            // Validate content length
            if (strlen($content) < 10) {
                $error = "Health tip must be at least 10 characters long";
                goto display_form;
            }
            
            if ($mysqli->query("UPDATE health_tips SET content = '$content' WHERE tip_id = $tip_id")) {
                $success = "Health tip updated successfully";
                goto display_form;
            } else {
                $error = "Error updating health tip: " . $mysqli->error;
                goto display_form;
            }
        }
        
        // Delete tip
        if ($action === 'delete' && isset($_POST['tip_id'])) {
            $tip_id = (int)$_POST['tip_id'];
            
            if ($mysqli->query("DELETE FROM health_tips WHERE tip_id = $tip_id")) {
                $success = "Health tip deleted successfully";
                goto display_form;
            } else {
                $error = "Error deleting health tip: " . $mysqli->error;
                goto display_form;
            }
        }
    }
}
display_form:

// Check if we're editing a tip
$edit_tip_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit_tip_content = '';
        
if ($edit_tip_id) {
    $result = $mysqli->query("SELECT content FROM health_tips WHERE tip_id = $edit_tip_id");
    if ($result && $row = $result->fetch_assoc()) {
        $edit_tip_content = $row['content'];
    }
}

// Fetch all health tips
$health_tips = [];
$result = $mysqli->query("SELECT * FROM health_tips ORDER BY created_at DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $health_tips[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Tips Management - Admin Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="overlay"></div>
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
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <h3 class="page-title">Health Tips Management</h3>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorAlert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Tip Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><?php echo $edit_tip_id ? 'Edit Health Tip' : 'Add New Health Tip'; ?></h5>
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="<?php echo $edit_tip_id ? 'edit' : 'add'; ?>">
                            <?php if ($edit_tip_id): ?>
                                <input type="hidden" name="tip_id" value="<?php echo $edit_tip_id; ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="content" class="form-label">Health Tip</label>
                                <textarea class="form-control" id="content" name="content" rows="3" required><?php echo $edit_tip_content; ?></textarea>
                                <div class="invalid-feedback">
                                    Please enter a health tip.
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <?php if ($edit_tip_id): ?>
                                    <a href="health-tips.php" class="btn btn-secondary">
                                        <i class="bi bi-x-lg me-2"></i> Cancel
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i> <?php echo $edit_tip_id ? 'Save Changes' : 'Add Tip'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Health Tips Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Content</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($health_tips as $tip): ?>
                                    <tr>
                                        <td><?php echo $tip['tip_id']; ?></td>
                                        <td><?php echo htmlspecialchars($tip['content']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($tip['created_at'])); ?></td>
                                        <td>
                                            <a href="health-tips.php?edit=<?php echo $tip['tip_id']; ?>" class="btn btn-warning btn-sm me-2">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" data-tip-id="<?php echo $tip['tip_id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal for delete confirmation (shared) -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalTitle">Delete Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteModalMessage">Are you sure you want to delete this item?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="tip_id" id="deleteTipId">
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin JS -->
    <script src="assets/js/admin.js"></script>
    
    <!-- Form Validation and Alert Fade -->
    <script>
        // Example starter JavaScript for disabling form submissions if there are invalid fields
        (function () {
            'use strict'

            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            const forms = document.querySelectorAll('.needs-validation')

            // Loop over them and prevent submission
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }

                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Auto fade out alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');

            if (successAlert) {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(successAlert);
                    bsAlert.close();
                }, 5000);
            }

            if (errorAlert) {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(errorAlert);
                    bsAlert.close();
                }, 5000);
            }

            // Delete confirmation modal
            const deleteConfirmModal = document.getElementById('deleteConfirmModal');
            const deleteModalTitle = document.getElementById('deleteModalTitle');
            const deleteModalMessage = document.getElementById('deleteModalMessage');
            const deleteTipId = document.getElementById('deleteTipId');
            const deleteForm = document.getElementById('deleteForm');

            deleteConfirmModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const tipId = button.getAttribute('data-tip-id');
                deleteTipId.value = tipId;
            });
        });
    </script>
</body>
</html>
