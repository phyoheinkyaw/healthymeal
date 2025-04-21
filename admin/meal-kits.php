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

// Helper function to get image URL from DB value
function get_meal_kit_image_url($image_url_db) {
    if (!$image_url_db) return 'https://placehold.co/120x90?text=No+Image';
    if (preg_match('/^https?:\/\//i', $image_url_db)) {
        return $image_url_db;
    }
    // Get the base URL up to the project root (e.g. /hm or /yourproject)
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectBase = '/' . $parts[0]; // e.g. '/hm'
    return $projectBase . '/uploads/meal-kits/' . $image_url_db;
}

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
    <style>
      input.form-control, select.form-select, textarea.form-control {
        border: 2px solid #b8b8b8 !important;
        box-shadow: 0 0 0 0.1rem #e0e0e0 !important;
        transition: border-color 0.2s, box-shadow 0.2s;
      }
      input.form-control:focus, select.form-select:focus, textarea.form-control:focus {
        border-color: #007bff !important;
        box-shadow: 0 0 0 0.2rem #b3d7ff !important;
      }
      /* Ingredient select: show 6 visible options, scroll after 6 */
      select.ingredient-select {
        height: auto !important;
        min-height: 38px;
        max-height: calc(6 * 38px);
        overflow-y: auto;
        display: block;
      }
      /* New grid-style for ingredient rows */
      #ingredientsList {
        display: grid;
        grid-template-columns: 2fr 1fr 60px;
        gap: 12px;
        margin-bottom: 8px;
      }
      .ingredient-row {
        display: contents;
      }
      .ingredient-select, .ingredient-quantity {
        width: 100%;
      }
      .ingredient-action {
        display: flex;
        align-items: center;
        justify-content: center;
      }
    </style>
    <style>
      /* Grid for all input fields in the modal */
      .modal-body .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px 32px;
        margin-bottom: 18px;
        align-items: end;
      }
      .form-grid .mb-3 {
        margin-bottom: 0 !important;
      }
      @media (max-width: 768px) {
        .modal-body .form-grid {
          grid-template-columns: 1fr;
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

    <!-- Meal Kit Modal (used for both add & edit) -->
    <div class="modal fade" id="mealKitModal" tabindex="-1" aria-labelledby="mealKitModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="mealKitModalLabel">Add Meal Kit</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <button type="button" class="btn btn-warning mb-3" id="fillDebugDataBtn">Fill With Random Data (Debug)</button>
            <form id="mealKitForm" autocomplete="off">
              <input type="hidden" id="mealKitId" name="mealKitId">
              <div class="form-grid">
                <div class="mb-3">
                  <label for="mealKitName" class="form-label">Name</label>
                  <input type="text" class="form-control" id="mealKitName" name="mealKitName" required>
                </div>
                <div class="mb-3">
                  <label for="categoryId" class="form-label">Category</label>
                  <select class="form-select" id="categoryId" name="categoryId" required>
                    <option value="" selected disabled>Select category</option>
                    <?php foreach ($categories as $category): ?>
                      <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="preparationPrice" class="form-label">Preparation Price ($)</label>
                  <input type="number" class="form-control" id="preparationPrice" name="preparationPrice" step="0.01" min="0" required>
                </div>
                <div class="mb-3">
                  <label for="cookingTime" class="form-label">Cooking Time (minutes)</label>
                  <input type="number" class="form-control" id="cookingTime" name="cookingTime" min="1" required>
                </div>
                <div class="mb-3">
                  <label for="servings" class="form-label">Servings</label>
                  <input type="number" class="form-control" id="servings" name="servings" min="1" required>
                </div>
                <div class="mb-3">
                  <label for="baseCalories" class="form-label">Base Calories (auto)</label>
                  <input type="number" class="form-control" id="baseCalories" name="baseCalories" readonly>
                </div>
                <div class="mb-3" style="grid-column: 1 / -1;">
                  <label class="form-label">Image</label>
                  <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2 mb-2">
                      <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="toggleImageInput">
                        <label class="form-check-label" for="toggleImageInput">Upload (Click to change)</label>
                      </div>
                    </div>
                    <div id="imageUrlInputWrapper" class="flex-grow-1">
                      <input type="url" class="form-control" id="imageUrl" name="imageUrl" placeholder="Paste image URL or upload" autocomplete="off">
                    </div>
                    <div id="imageFileInputWrapper" class="flex-grow-1 d-none">
                      <input type="file" class="form-control" id="imageFile" name="imageFile" accept="image/*">
                    </div>
                    <div id="imagePreviewWrapper" class="mt-2" style="display:none;">
                      <label class="form-label">Preview:</label>
                      <div>
                        <img id="imagePreview" src="#" alt="Image preview" class="img-thumbnail" style="max-width: 240px; max-height: 180px;">
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mb-3" style="grid-column: 1 / -1;">
                  <label for="mealKitDescription" class="form-label">Description</label>
                  <textarea class="form-control" id="mealKitDescription" name="mealKitDescription" rows="2" required></textarea>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Ingredients</label>
                <div id="ingredientsList"></div>
                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addIngredientBtn"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
              </div>
              <div class="modal-footer px-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="mealKitSubmitBtn">Add Meal Kit</button>
              </div>
            </form>
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
                        <button type="button" class="btn btn-primary" onclick="openMealKitModal('add')">
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
                                                    <?php $img_url = get_meal_kit_image_url($meal_kit['image_url']); ?>
                                                    <img src="<?php echo htmlspecialchars($img_url); ?>" style="max-width:120px; max-height:90px;" class="img-thumbnail" alt="Meal Kit Image">
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
                                                            data-action="edit-meal-kit" data-id="<?php echo $meal_kit['meal_kit_id']; ?>">
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

    <!-- New Ingredient Modal -->
    <div class="modal fade" id="newIngredientModal" tabindex="-1" aria-labelledby="newIngredientModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="newIngredientModalLabel">Add New Ingredient</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="newIngredientForm">
              <div class="mb-3">
                <label for="newIngredientName" class="form-label">Ingredient Name</label>
                <input type="text" class="form-control" id="newIngredientName" name="newIngredientName" required>
              </div>
              <div class="mb-3">
                <label for="newIngredientCalories" class="form-label">Calories per 100g</label>
                <input type="number" class="form-control" id="newIngredientCalories" name="newIngredientCalories" step="0.01" min="0" required>
              </div>
              <div class="mb-3">
                <label for="newIngredientProtein" class="form-label">Protein per 100g</label>
                <input type="number" class="form-control" id="newIngredientProtein" name="newIngredientProtein" step="0.01" min="0" required>
              </div>
              <div class="mb-3">
                <label for="newIngredientCarbs" class="form-label">Carbs per 100g</label>
                <input type="number" class="form-control" id="newIngredientCarbs" name="newIngredientCarbs" step="0.01" min="0" required>
              </div>
              <div class="mb-3">
                <label for="newIngredientFat" class="form-label">Fat per 100g</label>
                <input type="number" class="form-control" id="newIngredientFat" name="newIngredientFat" step="0.01" min="0" required>
              </div>
              <div class="mb-3">
                <label for="newIngredientPrice" class="form-label">Price per 100g</label>
                <input type="number" class="form-control" id="newIngredientPrice" name="newIngredientPrice" step="0.01" min="0" required>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="newIngredientIsMeat" name="newIngredientIsMeat">
                <label class="form-check-label" for="newIngredientIsMeat">Is Meat</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="newIngredientIsVegetarian" name="newIngredientIsVegetarian">
                <label class="form-check-label" for="newIngredientIsVegetarian">Is Vegetarian</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="newIngredientIsVegan" name="newIngredientIsVegan">
                <label class="form-check-label" for="newIngredientIsVegan">Is Vegan</label>
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="newIngredientIsHalal" name="newIngredientIsHalal">
                <label class="form-check-label" for="newIngredientIsHalal">Is Halal</label>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="newIngredientForm" class="btn btn-primary">Add Ingredient</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Ingredient data for JS -->
    <script>
      window.ingredientsList = <?php echo json_encode($ingredients); ?>;
      window.categoriesList = <?php echo json_encode($categories); ?>;
    </script>
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

    // Ingredient data from PHP
    const allIngredients = <?php echo json_encode($ingredients); ?>;

    // Dynamic ingredient row rendering, duplicate prevention, calorie calculation, and new ingredient handling will be implemented here.
    // For now, placeholder for UX improvement JS.

    // Toggle between image URL and file upload, and handle preview
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('toggleImageInput');
        const urlWrapper = document.getElementById('imageUrlInputWrapper');
        const fileWrapper = document.getElementById('imageFileInputWrapper');
        const urlInput = document.getElementById('imageUrl');
        const fileInput = document.getElementById('imageFile');
        const previewWrapper = document.getElementById('imagePreviewWrapper');
        const previewImg = document.getElementById('imagePreview');

        function showPreview(src) {
          if (src) {
            previewImg.src = src;
            previewWrapper.style.display = '';
          } else {
            previewImg.src = '#';
            previewWrapper.style.display = 'none';
          }
        }

        // Toggle logic
        if (toggle && urlWrapper && fileWrapper) {
          toggle.addEventListener('change', function() {
            if (toggle.checked) {
              urlWrapper.classList.add('d-none');
              fileWrapper.classList.remove('d-none');
              showPreview('');
            } else {
              fileWrapper.classList.add('d-none');
              urlWrapper.classList.remove('d-none');
              showPreview(urlInput.value);
            }
          });
        }

        // Preview for URL
        if (urlInput) {
          urlInput.addEventListener('input', function() {
            if (urlInput.value && !toggle.checked) {
              showPreview(urlInput.value);
            } else {
              showPreview('');
            }
          });
        }

        // Preview for file upload
        if (fileInput) {
          fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files[0]) {
              const reader = new FileReader();
              reader.onload = function(e) {
                showPreview(e.target.result);
              };
              reader.readAsDataURL(fileInput.files[0]);
            } else {
              showPreview('');
            }
          });
        }
    });
    </script>
</body>

</html>