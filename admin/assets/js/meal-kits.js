// Initialize when DOM is ready
$(document).ready(function() {
    // Add event listener for add ingredient button if it exists
    const addIngredientBtn = document.getElementById('addIngredientBtn');
    if (addIngredientBtn) {
        addIngredientBtn.addEventListener('click', function() {
            console.log('Adding new ingredient row');
            try {
                addIngredientRow();
            } catch (e) {
                console.error('addIngredientBtn exception:', e);
                alert('Error adding new ingredient row');
            }
        });
    }

    // Initialize dietary flags when modal is shown
    $('#addMealKitModal, #editMealKitModal').on('shown.bs.modal', function() {
        initializeDietaryFlags();
    });
});

// Array to track selected ingredients
let selectedIngredients = [];

// Handle dietary flag changes
function handleDietaryFlagChange() {
    const isMeat = document.getElementById('is_meat').checked;
    const isVegetarian = document.getElementById('is_vegetarian').checked;
    const isVegan = document.getElementById('is_vegan').checked;

    if (isMeat) {
        // If meat is checked, uncheck vegetarian and vegan
        document.getElementById('is_vegetarian').checked = false;
        document.getElementById('is_vegan').checked = false;
    }
}

function openIngredientModal() {
    // Show ingredient selection modal
    const modal = new bootstrap.Modal(document.getElementById('ingredientSelectionModal'));
    modal.show();
}

function addIngredientRow(ingredientId = '', quantity = '') {
    console.log('addIngredientRow called with ingredientId:', ingredientId, 'quantity:', quantity);
    try {
        const row = document.createElement('div');
        row.className = 'row mb-3 ingredient-row';
        row.innerHTML = `
            <div class="col-md-6">
                <select class="form-select ingredient-select" name="ingredients[]" required>
                    <option value="">Select Ingredient</option>
                    ${ingredientsList.map(ing => 
                        `<option value="${ing.ingredient_id}" ${ingredientId === ing.ingredient_id ? 'selected' : ''}>${ing.name}</option>`
                    ).join('')}
                    <option value="new">+ Add New Ingredient</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" class="form-control ingredient-quantity" name="quantities[]" 
                       placeholder="Quantity (g)" min="1" value="${quantity}" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger" onclick="this.closest('.ingredient-row').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        document.getElementById('ingredientsContainer').appendChild(row);
    } catch (e) {
        console.error('addIngredientRow exception:', e);
        alert('Error adding ingredient row');
    }
}

function showNewIngredientModal(row) {
    console.log('showNewIngredientModal called');
    try {
        const modalContent = `
            <div class="mb-3">
                <label class="form-label">Ingredient Name</label>
                <input type="text" class="form-control" id="newIngredientName" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Calories per 100g</label>
                <input type="number" class="form-control" id="newIngredientCalories" required min="0">
            </div>
            <div class="mb-3">
                <label class="form-label">Protein per 100g</label>
                <input type="number" class="form-control" id="newIngredientProtein" required min="0" step="0.1">
            </div>
            <div class="mb-3">
                <label class="form-label">Carbs per 100g</label>
                <input type="number" class="form-control" id="newIngredientCarbs" required min="0" step="0.1">
            </div>
            <div class="mb-3">
                <label class="form-label">Fat per 100g</label>
                <input type="number" class="form-control" id="newIngredientFat" required min="0" step="0.1">
            </div>
            <div class="mb-3">
                <label class="form-label">Price per 100g</label>
                <input type="number" class="form-control" id="newIngredientPrice" required min="0" step="0.01">
            </div>
        `;

        const modal = new bootstrap.Modal(document.getElementById('genericModal'));
        document.querySelector('#genericModal .modal-title').textContent = 'Add New Ingredient';
        document.querySelector('#genericModal .modal-body').innerHTML = modalContent;
        document.querySelector('#genericModal .modal-footer').innerHTML = `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveNewIngredient">Save</button>
        `;

        document.getElementById('saveNewIngredient').addEventListener('click', function () {
            console.log('Saving new ingredient');
            try {
                const newIngredient = {
                    name: document.getElementById('newIngredientName').value,
                    calories_per_100g: parseFloat(document.getElementById('newIngredientCalories').value),
                    protein_per_100g: parseFloat(document.getElementById('newIngredientProtein').value),
                    carbs_per_100g: parseFloat(document.getElementById('newIngredientCarbs').value),
                    fat_per_100g: parseFloat(document.getElementById('newIngredientFat').value),
                    price_per_100g: parseFloat(document.getElementById('newIngredientPrice').value)
                };

                // Save new ingredient via API
                fetch('api/ingredients/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(newIngredient)
                })
                .then(response => response.json())
                .then(data => {
                    console.log('saveNewIngredient success:', data);
                    if (data.success) {
                        // Add new ingredient to global list and select it
                        window.ingredients.push(data.ingredient);
                        const select = row.querySelector('.ingredient-select');
                        select.innerHTML = `
                        <option value="">Select Ingredient</option>
                        ${window.ingredients.map(ing =>
                            `<option value="${ing.ingredient_id}" ${ing.ingredient_id == data.ingredient.ingredient_id ? 'selected' : ''}>
                                ${ing.name} (${ing.calories_per_100g} cal/100g)
                            </option>`
                        ).join('')}
                        <option value="new">+ Add New Ingredient</option>
                    `;
                        modal.hide();
                    } else {
                        console.error('API error:', data.error);
                        showAlert('danger', data.message || 'Failed to create ingredient');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while creating ingredient');
                });
            } catch (e) {
                console.error('saveNewIngredient exception:', e);
                alert('Error saving new ingredient');
            }
        });

        modal.show();
    } catch (e) {
        console.error('showNewIngredientModal exception:', e);
        alert('Error showing new ingredient modal');
    }
}

// View Meal Kit Details
function viewMealKit(id) {
    console.log('viewMealKit called with id:', id);
    try {
        // Fetch meal kit details including ingredients
        fetch(`api/meal-kits/get.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                console.log('viewMealKit success:', data);
                if (data.success) {
                    const mealKit = data.meal_kit;
                    const ingredients = data.ingredients;

                    // Create modal content
                    let modalContent = `
                        <div class="row">
                            <div class="col-md-4">
                                ${mealKit.image_url
                                    ? `<img src="${mealKit.image_url}" class="img-fluid rounded" alt="${mealKit.name}">`
                                    : '<div class="placeholder-image bg-light d-flex align-items-center justify-content-center rounded" style="height: 200px;"><i class="bi bi-image text-muted fs-1"></i></div>'
                                }
                            </div>
                            <div class="col-md-8">
                                <h4>${mealKit.name}</h4>
                                <p class="text-muted">${mealKit.description}</p>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <strong>Category:</strong> ${mealKit.category_name}
                                    </div>
                                    <div class="col-6">
                                        <strong>Price:</strong> RM ${parseFloat(mealKit.preparation_price).toFixed(2)}
                                    </div>
                                    <div class="col-6">
                                        <strong>Base Calories:</strong> ${mealKit.base_calories} kcal
                                    </div>
                                    <div class="col-6">
                                        <strong>Dietary Info:</strong>
                                        <div class="dietary-info">
                                            ${mealKit.is_meat ? '<span class="badge bg-primary">Meat</span>' : ''}
                                            ${mealKit.is_vegetarian ? '<span class="badge bg-success">Vegetarian</span>' : ''}
                                            ${mealKit.is_vegan ? '<span class="badge bg-success">Vegan</span>' : ''}
                                            ${mealKit.is_halal ? '<span class="badge bg-info">Halal</span>' : ''}
                                        </div>
                                    </div>
                                    ${mealKit.cooking_time ? `<div class="col-6">
                                        <strong>Cooking Time:</strong> ${mealKit.cooking_time} minutes
                                    </div>` : ''}
                                    ${mealKit.servings ? `<div class="col-6">
                                        <strong>Servings:</strong> ${mealKit.servings}
                                    </div>` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mt-4">Ingredients</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Quantity (g)</th>
                                        <th>Calories</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${ingredients.map(ingredient => `
                                        <tr>
                                            <td>${ingredient.name}</td>
                                            <td>${ingredient.default_quantity}</td>
                                            <td>${(ingredient.calories_per_100g * ingredient.default_quantity / 100).toFixed(1)} kcal</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;

                    // Create and show modal
                    const modal = new bootstrap.Modal(document.getElementById('viewMealKitModal'));
                    document.getElementById('viewMealKitContent').innerHTML = modalContent;
                    modal.show();
                } else {
                    console.error('API error:', data.error);
                    showAlert('danger', data.message || 'Failed to load meal kit details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while loading meal kit details');
            });
    } catch (e) {
        console.error('viewMealKit exception:', e);
        showAlert('danger', 'An error occurred while loading meal kit details');
    }
}

// Edit Meal Kit
function editMealKit(id) {
    console.log('editMealKit called with id:', id);
    try {
        // Fetch meal kit details
        fetch(`api/meal-kits/get.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                console.log('editMealKit success:', data);
                if (data.success) {
                    const mealKit = data.meal_kit;
                    const ingredients = data.ingredients;

                    // Fill form with meal kit data
                    const form = document.getElementById('editMealKitForm');
                    form.querySelector('[name="meal_kit_id"]').value = mealKit.meal_kit_id;
                    form.querySelector('[name="name"]').value = mealKit.name;
                    form.querySelector('[name="description"]').value = mealKit.description;
                    form.querySelector('[name="category_id"]').value = mealKit.category_id;
                    form.querySelector('[name="preparation_price"]').value = mealKit.preparation_price;
                    form.querySelector('[name="base_calories"]').value = mealKit.base_calories;
                    form.querySelector('[name="cooking_time"]').value = mealKit.cooking_time;
                    form.querySelector('[name="servings"]').value = mealKit.servings;
                    form.querySelector('[name="image_url"]').value = mealKit.image_url;

                    // Set dietary flags
                    document.getElementById('is_meat').checked = mealKit.is_meat;
                    document.getElementById('is_vegetarian').checked = mealKit.is_vegetarian;
                    document.getElementById('is_vegan').checked = mealKit.is_vegan;
                    document.getElementById('is_halal').checked = mealKit.is_halal;

                    // Clear existing ingredients
                    const ingredientsContainer = document.getElementById('ingredientsContainer');
                    while (ingredientsContainer.firstChild) {
                        ingredientsContainer.removeChild(ingredientsContainer.firstChild);
                    }

                    // Add ingredients to form
                    ingredients.forEach(ingredient => {
                        addIngredientRow(ingredient.ingredient_id, ingredient.default_quantity);
                    });

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('editMealKitModal'));
                    modal.show();
                } else {
                    console.error('API error:', data.error);
                    showAlert('danger', data.message || 'Failed to load meal kit details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while loading meal kit details');
            });
    } catch (e) {
        console.error('editMealKit exception:', e);
        showAlert('danger', 'An error occurred while loading meal kit details');
    }
}

// Delete Meal Kit
function deleteMealKit(id) {
    console.log('deleteMealKit called with id:', id);
    try {
        if (confirm('Are you sure you want to delete this meal kit?')) {
            fetch(`api/meal-kits/delete.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                console.log('deleteMealKit success:', data);
                if (data.success) {
                    showAlert('success', 'Meal kit deleted successfully');
                    location.reload();
                } else {
                    console.error('API error:', data.error);
                    showAlert('danger', data.message || 'Failed to delete meal kit');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while deleting meal kit');
            });
        }
    } catch (e) {
        console.error('deleteMealKit exception:', e);
        showAlert('danger', 'An error occurred while deleting meal kit');
    }
}

// Toggle Meal Kit Status
function toggleMealKitStatus(id) {
    console.log('toggleMealKitStatus called with id:', id);
    try {
        fetch(`api/meal-kits/toggle-status.php?id=${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            console.log('toggleMealKitStatus success:', data);
            if (data.success) {
                showAlert('success', 'Meal kit status updated successfully');
                location.reload();
            } else {
                console.error('API error:', data.error);
                showAlert('danger', data.message || 'Failed to update meal kit status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while updating meal kit status');
        });
    } catch (e) {
        console.error('toggleMealKitStatus exception:', e);
        showAlert('danger', 'An error occurred while updating meal kit status');
    }
}

function handleIngredientSelect(selectElement) {
    console.log('handleIngredientSelect called');
    try {
        const ingredientId = selectElement.value;
        console.log('Selected ingredient ID:', ingredientId);
        
        if (ingredientId === 'new') {
            console.log('Showing add new ingredient modal');
            // Show add new ingredient modal
            const modal = new bootstrap.Modal(document.getElementById('addIngredientModal'));
            modal.show();
        } else {
            console.log('Adding selected ingredient');
            // Add selected ingredient to list
            const ingredientName = selectElement.options[selectElement.selectedIndex].text;
            const ingredientRow = selectElement.closest('.ingredient-row');
            const quantityInput = ingredientRow.querySelector('.quantity-input');
            
            // Calculate calories for this ingredient
            calculateTotalCalories(ingredientRow.closest('form'));
        }
    } catch (e) {
        console.error('handleIngredientSelect exception:', e);
        alert('Error in handleIngredientSelect function');
    }
}

// Calculate Total Calories
function calculateTotalCalories(form) {
    console.log('calculateTotalCalories called');
    try {
        let totalCalories = 0;
        const ingredientRows = form.querySelectorAll('.ingredient-row');
        
        ingredientRows.forEach(row => {
            const select = row.querySelector('.ingredient-select');
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            
            if (select.value !== '' && select.value !== 'new') {
                console.log('Processing ingredient:', select.value, 'quantity:', quantity);
                // Get calories from API or database
                const ingredientId = select.value;
                // This would need to be implemented with your actual API
                // For now, just using a placeholder
                const caloriesPerGram = 1; // Replace with actual value
                totalCalories += quantity * caloriesPerGram;
            }
        });
        
        console.log('Total calories calculated:', totalCalories);
        form.querySelector('.total-calories').textContent = totalCalories.toFixed(2);
    } catch (e) {
        console.error('calculateTotalCalories exception:', e);
        alert('Error calculating total calories');
    }
}

// Helper function to show alerts
function showAlert(type, message) {
    console.log('showAlert called with type:', type, 'message:', message);
    try {
        const alertsContainer = document.getElementById('alertsContainer');
        if (alertsContainer) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-transition`;
            alertDiv.role = 'alert';
            
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <strong>${message}</strong>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            alertsContainer.appendChild(alertDiv);
            
            // Add show class to trigger transition
            setTimeout(() => {
                alertDiv.classList.add('show');
            }, 100);

            // Initialize Bootstrap alert
            new bootstrap.Alert(alertDiv);

            // Remove alert after 3 seconds with transition
            setTimeout(() => {
                alertDiv.classList.remove('show');
                alertDiv.classList.add('fade-out');
                setTimeout(() => {
                    alertDiv.remove();
                }, 300);
            }, 3000);
        }
    } catch (e) {
        console.error('showAlert exception:', e);
        alert('Error showing alert');
    }
}

// Initialize dietary flags for the current form
function initializeDietaryFlags() {
    console.log('initializeDietaryFlags called');
    try {
        // Remove existing event listeners first
        const dietaryFlags = document.querySelectorAll('input[type="checkbox"][name^="is_"]');
        dietaryFlags.forEach(flag => {
            flag.removeEventListener('change', handleDietaryFlagChange);
        });

        // Add new event listeners
        dietaryFlags.forEach(flag => {
            flag.addEventListener('change', handleDietaryFlagChange);
        });
    } catch (e) {
        console.error('initializeDietaryFlags exception:', e);
        alert('Error initializing dietary flags');
    }
}