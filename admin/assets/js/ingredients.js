// Edit Ingredient
function editIngredient(id) {
    // Fetch ingredient details
    fetch(`api/ingredients/get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ingredient = data.ingredient;
                
                // Fill form with ingredient data
                const form = document.getElementById('editIngredientForm');
                form.querySelector('[name="ingredient_id"]').value = ingredient.ingredient_id;
                form.querySelector('[name="name"]').value = ingredient.name;
                form.querySelector('[name="calories_per_100g"]').value = ingredient.calories_per_100g;
                form.querySelector('[name="protein_per_100g"]').value = ingredient.protein_per_100g;
                form.querySelector('[name="carbs_per_100g"]').value = ingredient.carbs_per_100g;
                form.querySelector('[name="fat_per_100g"]').value = ingredient.fat_per_100g;
                form.querySelector('[name="price_per_100g"]').value = ingredient.price_per_100g;
                
                // Set dietary checkboxes
                form.querySelector('#editIsMeat').checked = ingredient.is_meat == 1;
                form.querySelector('#editIsVegetarian').checked = ingredient.is_vegetarian == 1;
                form.querySelector('#editIsVegan').checked = ingredient.is_vegan == 1;
                form.querySelector('#editIsHalal').checked = ingredient.is_halal == 1;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('editIngredientModal'));
                modal.show();
            } else {
                showAlert('danger', data.message || 'Failed to load ingredient details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while loading ingredient details');
        });
}

// Helper function to show alerts
function showAlert(type, message) {
    // Remove existing alerts
    const existingAlerts = document.getElementById('alertsContainer');
    if (existingAlerts) {
        existingAlerts.innerHTML = '';
    }

    // Create new alert
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
    
    // Add to container
    const container = document.getElementById('alertsContainer');
    if (container) {
        container.appendChild(alertDiv);
    }

    // Add show class to trigger transition
    setTimeout(() => {
        alertDiv.classList.add('show');
    }, 100); // Small delay to ensure element is in DOM

    // Initialize Bootstrap alert
    new bootstrap.Alert(alertDiv);

    // Remove alert after 3 seconds with transition
    setTimeout(() => {
        alertDiv.classList.remove('show');
        alertDiv.classList.add('fade-out');
        setTimeout(() => {
            alertDiv.remove();
        }, 300); // Wait for transition
    }, 3000);
}

// Save Ingredient
function saveIngredient() {
    const form = document.getElementById('addIngredientForm');
    const formData = new FormData(form);
    
    fetch('api/ingredients/save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            handleFormSuccess('Ingredient saved successfully');
            const modal = bootstrap.Modal.getInstance(document.getElementById('addIngredientModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            handleFormError(data.message || 'Failed to save ingredient');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        handleFormError('An error occurred while saving the ingredient');
    });
}

// Update Ingredient
function updateIngredient() {
    const form = document.getElementById('editIngredientForm');
    const formData = new FormData(form);
    
    fetch('api/ingredients/update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            handleFormSuccess('Ingredient updated successfully');
            const modal = bootstrap.Modal.getInstance(document.getElementById('editIngredientModal'));
            if (modal) {
                modal.hide();
            }
        } else {
            handleFormError(data.message || 'Failed to update ingredient');
        }
    })
    .catch(error => {
        console.error('error:', error);
        handleFormError('An error occurred while updating the ingredient');
    });
}

// Custom confirm modal for delete (dynamic, orders style)
function showDeleteConfirmModal(onConfirm, options = {}) {
    // Remove any existing modal
    $('#deleteConfirmModal').remove();
    const title = options.title || 'Delete Confirmation';
    const message = options.message || 'Are you sure you want to delete this item?';
    const icon = options.icon || '<i class="bi bi-trash-fill text-danger me-2"></i>';
    const modalHtml = `
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger-subtle">
            <h5 class="modal-title" id="deleteConfirmLabel">${icon}${title}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-0">${message}</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="deleteConfirmBtn">Yes, Delete</button>
          </div>
        </div>
      </div>
    </div>`;
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    modal.show();
    $('#deleteConfirmBtn').on('click', function() {
        modal.hide();
        if (onConfirm) onConfirm();
    });
    $('#deleteConfirmModal').on('hidden.bs.modal', function() {
        $('#deleteConfirmModal').remove();
    });
}

function deleteIngredient(id) {
    if (!id) {
        showAlert('danger', 'Please select an ingredient to delete');
        return;
    }
    showDeleteConfirmModal(function() {
        fetch('api/ingredients/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ingredient_id=${encodeURIComponent(id)}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                handleFormSuccess('Ingredient deleted successfully');
            } else {
                handleFormError(data.message || 'Failed to delete ingredient');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            handleFormError('An error occurred while deleting the ingredient');
        });
    }, {
        title: 'Delete Ingredient',
        message: 'Are you sure you want to delete this ingredient?',
        icon: '<i class="bi bi-trash-fill text-danger me-2"></i>'
    });
}

// Show success alert after page load if there's a success message in the URL
window.addEventListener('load', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    const errorMessage = urlParams.get('error');

    if (successMessage) {
        showAlert('success', successMessage);
        // Remove the parameter from URL
        const newUrl = window.location.pathname;
        history.replaceState({}, document.title, newUrl);
    } else if (errorMessage) {
        showAlert('danger', errorMessage); 
        // Remove the parameter from URL
        const newUrl = window.location.pathname;
        history.replaceState({}, document.title, newUrl);
    }
});

// Handle form submissions to show alerts after reload
function handleFormSuccess(message) {
    const url = new URL(window.location.href);
    url.searchParams.set('success', message);
    window.location.href = url.toString();
}

function handleFormError(message) {
    const url = new URL(window.location.href);
    url.searchParams.set('error', message);
    window.location.href = url.toString();
}