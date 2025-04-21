<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

// Fetch all ingredients
$ingredients = [];
$result = $mysqli->query("
    SELECT ingredient_id, name, calories_per_100g, protein_per_100g, carbs_per_100g, 
           fat_per_100g, price_per_100g 
    FROM ingredients
    ORDER BY name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ingredients[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingredients Management - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .alert-transition {
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease-in-out;
        }
        .alert-transition.show {
            opacity: 1;
            transform: translateY(0);
        }
        .alert-transition.fade-out {
            opacity: 0;
            transform: translateY(-20px);
        }
        
        /* Enhanced error alert styling */
        .alert-danger {
            border-color: #dc3545;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.15);
        }
        .alert-danger .btn-close {
            filter: brightness(0) saturate(100%) invert(16%) sepia(97%) saturate(6407%) hue-rotate(358deg) brightness(95%) contrast(96%);
        }
        
        /* Enhanced success alert styling */
        .alert-success {
            border-color: #28a745;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.15);
        }
        .alert-success .btn-close {
            filter: brightness(0) saturate(100%) invert(16%) sepia(97%) saturate(6407%) hue-rotate(358deg) brightness(95%) contrast(96%);
        }
        
        /* Input box border shade for ingredient forms */
        .ingredient-form .form-control {
            border: 2px solid #dee2e6;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            border-radius: 0.5rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .ingredient-form .form-control:focus {
            border-color: #6c63ff;
            box-shadow: 0 0 0 0.2rem rgba(108,99,255,0.15);
        }
        .ingredient-form .row {
            margin-left: -0.5rem;
            margin-right: -0.5rem;
        }
        .ingredient-form .col-md-6 {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        .ingredient-form .mb-3 {
            margin-bottom: 1.25rem;
        }
    </style>
</head>
<body>

<div class="overlay" onclick="toggleSidebar()" style="z-index: 1040"></div>
<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="sidebar-toggle">
        <button class="btn btn-dark" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <main class="main-content">
        <div class="alerts-container position-fixed top-0 start-0 w-100 p-3" style="z-index: 1060"></div>
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <h3 class="page-title">Ingredients Management</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addIngredientModal">
                        <i class="bi bi-plus-lg"></i> Add New Ingredient
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-3" id="alertsContainer"></div>
                            <div class="table-responsive">
                                <table class="table table-hover" id="ingredientsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Calories</th>
                                            <th>Protein</th>
                                            <th>Carbs</th>
                                            <th>Fat</th>
                                            <th>Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ingredients as $ingredient): ?>
                                            <tr>
                                                <td>#<?php echo $ingredient['ingredient_id']; ?></td>
                                                <td><?php echo htmlspecialchars($ingredient['name']); ?></td>
                                                <td><?php echo $ingredient['calories_per_100g']; ?> cal</td>
                                                <td><?php echo $ingredient['protein_per_100g']; ?>g</td>
                                                <td><?php echo $ingredient['carbs_per_100g']; ?>g</td>
                                                <td><?php echo $ingredient['fat_per_100g']; ?>g</td>
                                                <td>$<?php echo number_format($ingredient['price_per_100g'], 2); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="editIngredient(<?php echo $ingredient['ingredient_id']; ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteIngredient(<?php echo $ingredient['ingredient_id']; ?>)">
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
            </div>
        </div>
    </main>
</div>

<!-- Add Ingredient Modal -->
<div class="modal fade" id="addIngredientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Ingredient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addIngredientForm" class="ingredient-form">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Calories (per 100g)</label>
                            <input type="number" class="form-control" name="calories_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Protein (per 100g)</label>
                            <input type="number" class="form-control" name="protein_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Carbs (per 100g)</label>
                            <input type="number" class="form-control" name="carbs_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fat (per 100g)</label>
                            <input type="number" class="form-control" name="fat_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (per 100g)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="price_per_100g" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveIngredient()">Save Ingredient</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Ingredient Modal -->
<div class="modal fade" id="editIngredientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ingredient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editIngredientForm" class="ingredient-form">
                    <input type="hidden" name="ingredient_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Calories (per 100g)</label>
                            <input type="number" class="form-control" name="calories_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Protein (per 100g)</label>
                            <input type="number" class="form-control" name="protein_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Carbs (per 100g)</label>
                            <input type="number" class="form-control" name="carbs_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fat (per 100g)</label>
                            <input type="number" class="form-control" name="fat_per_100g" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (per 100g)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="price_per_100g" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateIngredient()">Update Ingredient</button>
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
<script src="assets/js/ingredients.js"></script>

</body>
</html>