<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Get flash message if exists
$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);

// Fetch all meal kits with calculated total calories
$meal_kits = [];
$result = $mysqli->query("
    SELECT 
        mk.*,
        c.name as category_name,
        COUNT(DISTINCT mki.ingredient_id) as ingredients_count,
        SUM(i.calories_per_100g * mki.default_quantity / 100) as total_calories
    FROM meal_kits mk
    LEFT JOIN categories c ON mk.category_id = c.category_id
    LEFT JOIN meal_kit_ingredients mki ON mk.meal_kit_id = mki.meal_kit_id
    LEFT JOIN ingredients i ON mki.ingredient_id = i.ingredient_id
    GROUP BY mk.meal_kit_id
    ORDER BY mk.is_active DESC, mk.created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $meal_kits[] = $row;
    }
}

// Fetch categories for dropdown
$categories = [];
$cat_result = $mysqli->query("SELECT * FROM categories ORDER BY name");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch ingredients for selection
$ingredients = [];
$ing_result = $mysqli->query("SELECT * FROM ingredients ORDER BY name");
if ($ing_result) {
    while ($row = $ing_result->fetch_assoc()) {
        $ingredients[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Kits Management - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
    .meal-kit-thumbnail {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
    }

    .alert-box {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1050;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .alert-box.fade-out {
        animation: slideOut 0.3s ease-in;
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    </style>
</head>

<body>
    <!-- Flash message container -->
    <?php if ($flash_message): ?>
    <div id="flashMessage" class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show position-fixed top-0 end-0 m-3 z-index-1061" role="alert" style="z-index: 1061 !important;">
        <?php echo htmlspecialchars($flash_message['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Alert container for JavaScript alerts -->
    <div id="alertContainer" class="position-fixed top-0 end-0 m-3 z-index-1061" style="z-index: 1061 !important;">
    </div>

    <!-- Modal for delete confirmation -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Meal Kit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this meal kit?</p>
                    <p class="text-warning" id="orderWarning" style="display: none;">
                        Warning: This meal kit has active orders. It will be marked as inactive instead of being deleted.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

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
                        <h3 class="page-title">Meal Kits Management</h3>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addMealKitModal">
                            <i class="bi bi-plus-lg"></i> Add New Meal Kit
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="mealKitsTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Image</th>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Price</th>
                                                <th>Calories</th>
                                                <th>Ingredients</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($meal_kits as $meal_kit): ?>
                                            <tr <?php if (!$meal_kit['is_active']) echo 'class="text-muted"' ?> data-meal-kit-id="<?php echo $meal_kit['meal_kit_id']; ?>">
                                                <td>#<?php echo $meal_kit['meal_kit_id']; ?></td>
                                                <td>
                                                    <?php if (strpos($meal_kit['image_url'], 'http') === 0): ?>
                                                    <img src="<?php echo htmlspecialchars($meal_kit['image_url']); ?>"
                                                        alt="<?php echo htmlspecialchars($meal_kit['name']); ?>"
                                                        class="meal-kit-thumbnail">
                                                    <?php elseif ($meal_kit['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars('..' .$meal_kit['image_url']); ?>"
                                                        alt="<?php echo htmlspecialchars($meal_kit['name']); ?>"
                                                        class="meal-kit-thumbnail">
                                                    <?php else: ?>
                                                    <div
                                                        class="meal-kit-thumbnail bg-light d-flex align-items-center justify-content-center w-100 h-100 rounded">
                                                        <i class="bi bi-image text-muted h1"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($meal_kit['name']); ?></div>
                                                    <div class="small text-muted">
                                                        <?php echo substr(htmlspecialchars($meal_kit['description']), 0, 50) . '...'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($meal_kit['category_name']); ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($meal_kit['preparation_price'], 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <?php echo round($meal_kit['total_calories'] ?? $meal_kit['base_calories']); ?>
                                                        cal
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo $meal_kit['ingredients_count']; ?> items
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $meal_kit['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $meal_kit['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                            onclick="viewMealKit(<?php echo $meal_kit['meal_kit_id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                            onclick="editMealKit(<?php echo $meal_kit['meal_kit_id']; ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($meal_kit['is_active']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="deleteMealKit(<?php echo $meal_kit['meal_kit_id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if (!$meal_kit['is_active']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success"
                                                            onclick="toggleMealKitStatus(<?php echo $meal_kit['meal_kit_id']; ?>, true)"
                                                            title="This meal kit is inactive. Click to activate it instead of deleting.">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                        <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            onclick="toggleMealKitStatus(<?php echo $meal_kit['meal_kit_id']; ?>, false)"
                                                            title="Click to deactivate this meal kit">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                        <?php endif; ?>
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

    <!-- Scripts -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Admin JS -->
    <script src="assets/js/admin.js"></script>
    <!-- Meal Kits JS -->
    <script src="assets/js/meal-kits.js"></script>
    
    <script>
    // Remove flash message after 3 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const flashMessage = document.getElementById('flashMessage');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.classList.add('fade-out');
                setTimeout(() => {
                    flashMessage.remove();
                }, 300);
            }, 3000);
        }

        // Initialize DataTable
        const table = $('#mealKitsTable').DataTable({
            order: [[0, 'desc']],
            responsive: true,
            language: {
                search: "Search meal kits:",
                lengthMenu: "Show _MENU_ meal kits per page",
                info: "Showing _START_ to _END_ of _TOTAL_ meal kits",
                emptyTable: "No meal kits available"
            }
        });
    });

    // Toggle meal kit status
    function toggleMealKitStatus(mealKitId, isActive) {
        // Show loading state
        const toggleButton = document.querySelector(`button[onclick="toggleMealKitStatus(${mealKitId}, ${isActive})"]`);
        if (!toggleButton) return;
        
        const originalHTML = toggleButton.innerHTML;
        toggleButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        toggleButton.disabled = true;
        
        // Make API call to update status and reload page
        fetch(`api/meal-kits/toggle-status.php?id=${mealKitId}`)
            .then(response => {
                // No need to parse response, just reload the page
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                // Reset button on error
                toggleButton.innerHTML = originalHTML;
                toggleButton.disabled = false;
            });
    }

    // Delete meal kit
    function deleteMealKit(mealKitId) {
        console.log('Starting delete process for meal kit:', mealKitId);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();

        // Check for orders first
        fetch(`api/meal-kits/check-orders.php?id=${mealKitId}`)
            .then(response => {
                console.log('Check orders response status:', response.status);
                if (!response.ok) {
                    throw new Error('Failed to check meal kit status. Status: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Check orders raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Check orders parsed data:', data);
                    
                    if (!data.success) {
                        throw new Error(data.message || 'Failed to check meal kit status');
                    }

                    const orderWarning = document.getElementById('orderWarning');
                    const confirmButton = document.getElementById('confirmDelete');
                    
                    // If there are orders, we'll just deactivate instead of delete
                    if (data.has_orders) {
                        console.log('Meal kit has orders, showing warning');
                        orderWarning.style.display = 'block';
                        confirmButton.textContent = 'Mark as Inactive';
                        confirmButton.addEventListener('click', () => {
                            console.log('Marking meal kit as inactive');
                            // Mark as inactive
                            toggleMealKitStatus(mealKitId, false);
                            modal.hide();
                        });
                    } else {
                        console.log('No orders found, showing delete confirmation');
                        orderWarning.style.display = 'none';
                        confirmButton.textContent = 'Delete';
                        confirmButton.addEventListener('click', () => {
                            console.log('Deleting meal kit...');
                            // Delete meal kit
                            fetch(`api/meal-kits/delete.php?id=${mealKitId}`, {
                                method: 'DELETE'
                            })
                            .then(response => {
                                console.log('Delete response status:', response.status);
                                if (!response.ok) {
                                    throw new Error('Failed to delete meal kit. Status: ' + response.status);
                                }
                                return response.text();
                            })
                            .then(text => {
                                console.log('Delete raw response:', text);
                                try {
                                    const data = JSON.parse(text);
                                    console.log('Delete parsed data:', data);
                                    
                                    if (data.success) {
                                        // Show success alert
                                        showAlert('success', 'Meal kit deleted successfully');
                                        // Reload page after 1 second
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 1000);
                                    } else {
                                        throw new Error(data.message || 'Failed to delete meal kit');
                                    }
                                } catch (parseError) {
                                    console.error('Error parsing delete response:', parseError);
                                    throw new Error('Failed to parse delete response');
                                }
                            })
                            .catch(error => {
                                console.error('Delete error:', error);
                                showAlert('danger', error.message);
                            });
                            modal.hide();
                        });
                    }
                } catch (parseError) {
                    console.error('Error parsing check orders response:', parseError);
                    throw new Error('Failed to parse check orders response');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', error.message);
            });
    }

    // Show alert
    function showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        alertContainer.appendChild(alert);
        
        // Remove alert after 3 seconds
        setTimeout(() => {
            alert.remove();
        }, 3000);
    }
    </script>
</body>

</html>