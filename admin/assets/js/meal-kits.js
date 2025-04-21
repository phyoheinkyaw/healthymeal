// Initialize when DOM is ready
$(document).ready(function() {
    // Initialize dietary flags when modal is shown
    $('#mealKitModal').on('shown.bs.modal', function() {
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

// Utility to get array of selected ingredient IDs
function getSelectedIngredientIds() {
    return Array.from(document.querySelectorAll('#ingredientsList .ingredient-select'))
        .map(sel => sel.value)
        .filter(val => val && val !== 'new');
}

// Update all ingredient selects to prevent duplicate selection
function updateIngredientSelectOptions() {
    const selectedIds = getSelectedIngredientIds();
    document.querySelectorAll('#ingredientsList .ingredient-select').forEach(select => {
        const currentVal = select.value;
        select.innerHTML = '<option value="">Select Ingredient</option>' +
            ingredientsList.map(ing => {
                const disabled = selectedIds.includes(String(ing.ingredient_id)) && String(ing.ingredient_id) !== currentVal ? 'disabled' : '';
                return `<option value="${ing.ingredient_id}" data-calories="${ing.calories_per_100g}" ${currentVal == ing.ingredient_id ? 'selected' : ''} ${disabled}>${ing.name}</option>`;
            }).join('') +
            '<option value="new">+ Add New Ingredient</option>';
    });
}

// Calculate base calories
function updateBaseCalories() {
    let totalCalories = 0;
    document.querySelectorAll('#ingredientsList .ingredient-row').forEach(row => {
        const select = row.querySelector('.ingredient-select');
        const qtyInput = row.querySelector('.ingredient-quantity');
        const ingId = select.value;
        const qty = parseFloat(qtyInput.value);
        if (ingId && ingId !== 'new' && qty && !isNaN(qty)) {
            const ing = ingredientsList.find(i => String(i.ingredient_id) === ingId);
            if (ing) {
                totalCalories += (parseFloat(ing.calories_per_100g) * qty) / 100;
            }
        }
    });
    document.getElementById('baseCalories').value = Math.round(totalCalories);
}

function addIngredientRow(ingredientId = '', quantity = '') {
    console.log('addIngredientRow called with ingredientId:', ingredientId, 'quantity:', quantity);
    try {
        const row = document.createElement('div');
        row.className = 'ingredient-row';
        row.innerHTML = `
            <select class="form-select ingredient-select" name="ingredients[]" required>
                <option value="">Select Ingredient</option>
                ${ingredientsList.map(ing => 
                    `<option value="${ing.ingredient_id}" ${ingredientId == ing.ingredient_id ? 'selected' : ''}>${ing.name}</option>`
                ).join('')}
                <option value="new">+ Add New Ingredient</option>
            </select>
            <input type="number" class="form-control ingredient-quantity" name="quantities[]" 
                   placeholder="Quantity (g)" min="1" value="${quantity}" required>
            <div class="ingredient-action">
                <button type="button" class="btn btn-danger" onclick="this.closest('.ingredient-row').remove(); updateIngredientSelectOptions(); updateBaseCalories();">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        document.getElementById('ingredientsList').appendChild(row);
        updateIngredientSelectOptions();
        updateBaseCalories();
        // Add event listeners for dynamic updates
        const selectEl = row.querySelector('.ingredient-select');
        selectEl.addEventListener('change', function() {
            if (this.value === 'new') {
                showNewIngredientModal(row);
            } else {
                updateIngredientSelectOptions();
                updateBaseCalories();
            }
        });
        row.querySelector('.ingredient-quantity').addEventListener('input', function() {
            updateBaseCalories();
        });
    } catch (e) {
        console.error('addIngredientRow exception:', e);
        alert('Error adding ingredient row');
    }
}

const addIngredientBtn = document.getElementById('addIngredientBtn');
if (addIngredientBtn) {
    addIngredientBtn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Adding new ingredient row');
        try {
            addIngredientRow();
        } catch (e) {
            console.error('addIngredientBtn exception:', e);
            alert('Error adding new ingredient row');
        }
    });
}

// Prevent reselecting 'Select Ingredient' after selection
$(document).on('change', '#ingredientsList .ingredient-select', function() {
    if (this.value && this.value !== '' && this.value !== 'new') {
        // Disable this select to prevent changing after selection
        $(this).prop('disabled', true);
    }
    updateIngredientSelectOptions();
    updateBaseCalories();
});

function showNewIngredientModal(row) {
    const modal = new bootstrap.Modal(document.getElementById('newIngredientModal'));
    const form = document.getElementById('newIngredientForm');
    const nameInput = document.getElementById('newIngredientName');
    const errorDivId = 'ingredientNameError';
    // Remove any previous error
    let errorDiv = document.getElementById(errorDivId);
    if (errorDiv) errorDiv.remove();
    form.reset();
    modal.show();
    form.onsubmit = async function(e) {
        e.preventDefault();
        // Remove previous error
        let errorDiv = document.getElementById(errorDivId);
        if (errorDiv) errorDiv.remove();
        // Gather new ingredient data
        const name = nameInput.value.trim();
        const calories = document.getElementById('newIngredientCalories').value;
        const protein = document.getElementById('newIngredientProtein').value;
        const carbs = document.getElementById('newIngredientCarbs').value;
        const fat = document.getElementById('newIngredientFat').value;
        const price = document.getElementById('newIngredientPrice').value;
        const isMeat = document.getElementById('newIngredientIsMeat').checked;
        const isVegetarian = document.getElementById('newIngredientIsVegetarian').checked;
        const isVegan = document.getElementById('newIngredientIsVegan').checked;
        const isHalal = document.getElementById('newIngredientIsHalal').checked;
        // Validate name uniqueness (case-insensitive)
        const exists = ingredientsList.some(ing => ing.name.trim().toLowerCase() === name.toLowerCase());
        if (!name) {
          showIngredientNameError('Name is required');
          nameInput.focus();
          return;
        }
        if (exists) {
          showIngredientNameError('Ingredient with this name already exists');
          nameInput.focus();
          return;
        }
        // Send to backend
        try {
          const response = await fetch('api/ingredients/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name,
                calories_per_100g: calories,
                protein_per_100g: protein,
                carbs_per_100g: carbs,
                fat_per_100g: fat,
                price_per_100g: price,
                is_meat: isMeat,
                is_vegetarian: isVegetarian,
                is_vegan: isVegan,
                is_halal: isHalal
            })
          });
          const data = await response.json();
          if (data.success && data.ingredient) {
            ingredientsList.push(data.ingredient);
            updateIngredientSelectOptions();
            modal.hide();
            // Set the select in the row to the new ingredient
            const select = row.querySelector('.ingredient-select');
            select.value = data.ingredient.ingredient_id;
            select.dispatchEvent(new Event('change'));
          } else {
            showIngredientNameError('Failed to add new ingredient: ' + (data.message || 'Unknown error'));
          }
        } catch (err) {
          showIngredientNameError('Error adding ingredient: ' + err.message);
        }
        function showIngredientNameError(msg) {
          let errorDiv = document.createElement('div');
          errorDiv.id = errorDivId;
          errorDiv.className = 'text-danger small mb-2';
          errorDiv.textContent = msg;
          nameInput.parentNode.insertBefore(errorDiv, nameInput.nextSibling);
        }
    };
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

                    // Determine image URL (handle uploads vs. URLs)
                    let imgUrl = mealKit.image_url;
                    if (imgUrl && !/^https?:\/\//i.test(imgUrl)) {
                        // Uploaded file: prepend project base
                        const projectBase = window.location.pathname.split('/')[1];
                        imgUrl = `/${projectBase}/uploads/meal-kits/${imgUrl}`;
                    }
                    if (!imgUrl) {
                        imgUrl = 'https://placehold.co/320x180?text=No+Image';
                    }

                    // Compute dietary badges from ingredients
                    let hasMeat = false, isVegetarian = true, isVegan = true, isHalal = true;
                    ingredients.forEach(ing => {
                        if (ing.is_meat) hasMeat = true;
                        if (!ing.is_vegetarian) isVegetarian = false;
                        if (!ing.is_vegan) isVegan = false;
                        if (!ing.is_halal) isHalal = false;
                    });

                    // Create modal content
                    let modalContent = `
                        <div class="row g-4 align-items-center">
                            <div class="col-md-4 text-center">
                                <img src="${imgUrl}" class="img-fluid rounded shadow-sm border" alt="${mealKit.name}" style="max-height:220px;object-fit:cover;background:#f8f9fa;">
                            </div>
                            <div class="col-md-8">
                                <h4 class="fw-bold mb-1">${mealKit.name}</h4>
                                <div class="mb-2 text-secondary small">${mealKit.category_name || ''}</div>
                                <div class="mb-2">
                                    <span class="badge bg-primary">$ ${parseFloat(mealKit.preparation_price).toFixed(2)}</span>
                                    <span class="badge bg-secondary">${mealKit.base_calories} cal</span>
                                    ${mealKit.cooking_time ? `<span class="badge bg-info">${mealKit.cooking_time} min</span>` : ''}
                                    ${mealKit.servings ? `<span class="badge bg-success">${mealKit.servings} servings</span>` : ''}
                                </div>
                                <div class="mb-2">
                                    ${hasMeat ? '<span class="badge bg-danger">Meat</span>' : ''}
                                    ${isVegetarian ? '<span class="badge bg-success">Vegetarian</span>' : ''}
                                    ${isVegan ? '<span class="badge bg-success">Vegan</span>' : ''}
                                    ${isHalal ? '<span class="badge bg-info">Halal</span>' : ''}
                                </div>
                                <div class="mb-2 text-muted">${mealKit.description}</div>
                            </div>
                        </div>
                        <h5 class="mt-4 mb-2">Ingredients</h5>
                        <div class="table-responsive rounded shadow-sm">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
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
                                            <td>${(ingredient.calories_per_100g * ingredient.default_quantity / 100).toFixed(1)} cal</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;

                    // Create and show modal
                    let modalEl = document.getElementById('viewMealKitModal');
                    if (!modalEl) {
                        modalEl = document.createElement('div');
                        modalEl.className = 'modal fade';
                        modalEl.id = 'viewMealKitModal';
                        modalEl.tabIndex = -1;
                        modalEl.setAttribute('aria-labelledby', 'viewMealKitModalLabel');
                        modalEl.setAttribute('aria-hidden', 'true');
                        modalEl.innerHTML = `
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="viewMealKitModalLabel">Meal Kit Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" id="viewMealKitContent"></div>
                                </div>
                            </div>`;
                        document.body.appendChild(modalEl);
                    }
                    document.getElementById('viewMealKitContent').innerHTML = modalContent;
                    const modal = new bootstrap.Modal(modalEl);
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

// Unified modal logic for both add and edit meal kits
function openMealKitModal(mode, mealKitData = null) {
    const modal = new bootstrap.Modal(document.getElementById('mealKitModal'));
    if (mode === 'add') {
        resetMealKitFormToAdd();
        document.getElementById('mealKitForm').setAttribute('data-mode', 'add');
        modal.show();
        // Defensive: always clear after modal is hidden
        $('#mealKitModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
            resetMealKitFormToAdd();
        });
    } else if (mode === 'edit' && mealKitData) {
        const form = document.getElementById('mealKitForm');
        form.setAttribute('data-mode', 'edit');
        const title = document.getElementById('mealKitModalLabel');
        const submitBtn = document.getElementById('mealKitSubmitBtn');
        const mealKitIdInput = document.getElementById('mealKitId');
        form.reset();
        document.getElementById('ingredientsList').innerHTML = '';
        title.textContent = 'Edit Meal Kit';
        submitBtn.textContent = 'Save Changes';
        mealKitIdInput.value = mealKitData.meal_kit_id;
        document.getElementById('mealKitName').value = mealKitData.name;
        document.getElementById('categoryId').value = mealKitData.category_id;
        document.getElementById('preparationPrice').value = mealKitData.preparation_price;
        document.getElementById('cookingTime').value = mealKitData.cooking_time;
        document.getElementById('servings').value = mealKitData.servings;
        document.getElementById('mealKitDescription').value = mealKitData.description;
        document.getElementById('baseCalories').value = mealKitData.base_calories || '';
        // Image logic
        if (mealKitData.image_url && mealKitData.image_url.startsWith('http')) {
            document.getElementById('imageUrl').value = mealKitData.image_url;
            document.getElementById('imagePreview').src = mealKitData.image_url;
            document.getElementById('imagePreviewWrapper').style.display = 'block';
            document.getElementById('toggleImageInput').checked = false;
            document.getElementById('imageFileInputWrapper').classList.add('d-none');
            document.getElementById('imageUrlInputWrapper').classList.remove('d-none');
        } else if (mealKitData.image_url) {
            document.getElementById('imageUrl').value = '';
            // Always add /hm at the start for local images
            document.getElementById('imagePreview').src = '/hm/uploads/meal-kits/' + mealKitData.image_url;
            document.getElementById('imagePreviewWrapper').style.display = 'block';
            document.getElementById('toggleImageInput').checked = true;
            document.getElementById('imageFileInputWrapper').classList.remove('d-none');
            document.getElementById('imageUrlInputWrapper').classList.add('d-none');
        } else {
            document.getElementById('imageUrl').value = '';
            document.getElementById('imagePreview').src = '#';
            document.getElementById('imagePreviewWrapper').style.display = 'none';
            document.getElementById('toggleImageInput').checked = false;
            document.getElementById('imageFileInputWrapper').classList.add('d-none');
            document.getElementById('imageUrlInputWrapper').classList.remove('d-none');
        }

        // --- Fix: ensure ingredients show up in edit modal ---
        // Defensive: if mealKitData.ingredients is not present, try mealKitData.ingredient or fallback to arguments
        let ingredientsToShow = [];
        // Try to get ingredients from the API response directly (not just mealKitData)
        if (window.lastMealKitApiData && Array.isArray(window.lastMealKitApiData.ingredients) && window.lastMealKitApiData.ingredients.length > 0) {
            ingredientsToShow = window.lastMealKitApiData.ingredients;
        } else if (mealKitData.ingredients && Array.isArray(mealKitData.ingredients) && mealKitData.ingredients.length > 0) {
            ingredientsToShow = mealKitData.ingredients;
        } else if (Array.isArray(mealKitData.ingredient) && mealKitData.ingredient.length > 0) {
            ingredientsToShow = mealKitData.ingredient;
        }
        if (ingredientsToShow.length > 0) {
            ingredientsToShow.forEach(ing => {
                // Try both possible keys: ingredient_id or id
                addIngredientRow(ing.ingredient_id || ing.id, ing.default_quantity || ing.quantity);
            });
        } else {
            // If no ingredients, show a blank row
            addIngredientRow();
        }
        updateIngredientSelectOptions();
        updateBaseCalories();
        // Defensive: always clear after modal is hidden
        $('#mealKitModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
            resetMealKitFormToAdd();
        });
    }
    // Always show modal
    modal.show();
}

function resetMealKitFormToAdd() {
    const form = document.getElementById('mealKitForm');
    const title = document.getElementById('mealKitModalLabel');
    const submitBtn = document.getElementById('mealKitSubmitBtn');
    const mealKitIdInput = document.getElementById('mealKitId');
    // Reset all fields and preview
    form.reset();
    document.getElementById('mealKitName').value = '';
    document.getElementById('categoryId').value = '';
    document.getElementById('preparationPrice').value = '';
    document.getElementById('cookingTime').value = '';
    document.getElementById('servings').value = '';
    document.getElementById('mealKitDescription').value = '';
    document.getElementById('baseCalories').value = '';
    document.getElementById('imageUrl').value = '';
    document.getElementById('imageFile').value = '';
    document.getElementById('imagePreview').src = '#';
    document.getElementById('imagePreviewWrapper').style.display = 'none';
    document.getElementById('toggleImageInput').checked = false;
    document.getElementById('imageFileInputWrapper').classList.add('d-none');
    document.getElementById('imageUrlInputWrapper').classList.remove('d-none');
    document.getElementById('ingredientsList').innerHTML = '';
    addIngredientRow();
    form.setAttribute('data-mode', 'add');
    title.textContent = 'Add Meal Kit';
    submitBtn.textContent = 'Add Meal Kit';
    mealKitIdInput.value = '';
}

// Handle form submission for both add & edit
$(document).on('submit', '#mealKitForm', function(e) {
    e.preventDefault();
    const mode = this.getAttribute('data-mode');
    // Build FormData with correct field names for backend
    const formData = new FormData();
    if (mode === 'edit') {
        formData.append('meal_kit_id', document.getElementById('mealKitId').value);
    }
    formData.append('name', document.getElementById('mealKitName').value.trim());
    formData.append('description', document.getElementById('mealKitDescription').value.trim());
    formData.append('category_id', document.getElementById('categoryId').value);
    formData.append('preparation_price', document.getElementById('preparationPrice').value);
    formData.append('base_calories', document.getElementById('baseCalories').value);
    formData.append('cooking_time', document.getElementById('cookingTime').value);
    formData.append('servings', document.getElementById('servings').value);
    // Gather ingredients for add/edit
    let ingredients = [];
    let quantities = {};
    document.querySelectorAll('#ingredientsList .ingredient-row').forEach(row => {
        const sel = row.querySelector('.ingredient-select');
        const qty = row.querySelector('.ingredient-quantity');
        if (sel && qty && sel.value && sel.value !== 'new') {
            if (mode === 'add') {
                ingredients.push({ id: parseInt(sel.value), quantity: parseInt(qty.value) });
            } else {
                ingredients.push(parseInt(sel.value));
                quantities[sel.value] = parseInt(qty.value);
            }
        }
    });
    if (mode === 'add') {
        formData.append('ingredients', JSON.stringify(ingredients));
    } else {
        formData.append('ingredients', JSON.stringify(ingredients));
        formData.append('quantities', JSON.stringify(quantities));
    }
    // Handle image input
    const isUpload = document.getElementById('toggleImageInput').checked;
    if (isUpload) {
        formData.delete('imageUrl');
        const fileInput = document.getElementById('imageFile');
        if (fileInput && fileInput.files.length > 0) {
            formData.append('imageFile', fileInput.files[0]);
        }
    } else {
        formData.delete('imageFile');
        formData.append('image_url', document.getElementById('imageUrl').value.trim());
    }
    let url = '';
    if (mode === 'add') {
        url = 'api/meal-kits/save.php';
    } else if (mode === 'edit') {
        url = 'api/meal-kits/update.php';
    }
    if (!mode || !url) {
        alert('Form mode (add/edit) is missing or invalid. Cannot save!');
        console.error('Form submission error: mode is', mode, 'url is', url);
        return;
    }
    console.log('Submitting to:', url); // Debug: log the URL before making the request
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log('Meal kit save response:', data); // Debug
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error saving meal kit');
            // Debug: show full response in console
            console.error('Meal kit save error:', data);
        }
    })
    .catch((err) => {
        alert('Error saving meal kit');
        // Debug: show full error in console
        console.error('Meal kit save exception:', err);
    });
});

// Replace old add/edit triggers
$(document).on('click', '[data-action="add-meal-kit"]', function() {
    openMealKitModal('add');
});
$(document).on('click', '[data-action="edit-meal-kit"]', function() {
    const mealKitId = $(this).data('id');
    fetch('api/meal-kits/get.php?id=' + mealKitId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.lastMealKitApiData = data;
                openMealKitModal('edit', data.meal_kit);
            } else {
                alert('Meal kit not found');
            }
        });
});

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

                    // Dynamically create edit modal and form if not exists
                    let modalEl = document.getElementById('editMealKitModal');
                    if (!modalEl) {
                        modalEl = document.createElement('div');
                        modalEl.className = 'modal fade';
                        modalEl.id = 'editMealKitModal';
                        modalEl.tabIndex = -1;
                        modalEl.setAttribute('aria-labelledby', 'editMealKitModalLabel');
                        modalEl.setAttribute('aria-hidden', 'true');
                        modalEl.innerHTML = `
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editMealKitModalLabel">Edit Meal Kit</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="editMealKitForm" autocomplete="off">
                                            <input type="hidden" name="meal_kit_id">
                                            <div class="form-grid">
                                                <div class="mb-3">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" class="form-control" name="name" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Category</label>
                                                    <select class="form-select" name="category_id" required></select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Price</label>
                                                    <input type="number" class="form-control" name="preparation_price" min="0" step="0.01" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Base Calories</label>
                                                    <input type="number" class="form-control" name="base_calories" min="0" step="0.1" required readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Cooking Time (min)</label>
                                                    <input type="number" class="form-control" name="cooking_time" min="0" step="1">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Servings</label>
                                                    <input type="number" class="form-control" name="servings" min="1" step="1">
                                                </div>
                                                <div class="mb-3" style="grid-column: 1 / -1;">
                                                    <label class="form-label">Image</label>
                                                    <div class="d-flex flex-column gap-2">
                                                        <div class="d-flex align-items-center gap-2 mb-2">
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="editToggleImageInput">
                                                                <label class="form-check-label" for="editToggleImageInput">Upload (Click to change)</label>
                                                            </div>
                                                        </div>
                                                        <div id="editImageUrlInputWrapper" class="flex-grow-1">
                                                            <input type="url" class="form-control" id="editImageUrl" name="imageUrl" placeholder="Paste image URL or upload" autocomplete="off">
                                                        </div>
                                                        <div id="editImageFileInputWrapper" class="flex-grow-1 d-none">
                                                            <input type="file" class="form-control" id="editImageFile" name="imageFile" accept="image/*">
                                                        </div>
                                                        <div id="editImagePreviewWrapper" class="mt-2" style="display:none;">
                                                            <label class="form-label">Preview:</label>
                                                            <div>
                                                                <img id="editImagePreview" src="#" alt="Image preview" class="img-thumbnail" style="max-width: 240px; max-height: 180px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-text">If you want to update the image, toggle to upload or paste a new URL. Only one will be used.</div>
                                                </div>
                                                <div class="mb-3" style="grid-column: 1 / -1;">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="description" rows="2" required></textarea>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Ingredients</label>
                                                <div id="editIngredientsList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;"></div>
                                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addEditIngredientBtn"><i class="bi bi-plus-lg"></i> Add Ingredient</button>
                                            </div>
                                            <div class="modal-footer px-0">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>`;
                        document.body.appendChild(modalEl);
                    }

                    // Fill form fields
                    const form = modalEl.querySelector('#editMealKitForm');
                    form.reset();
                    form.querySelector('[name="meal_kit_id"]').value = mealKit.meal_kit_id;
                    form.querySelector('[name="name"]').value = mealKit.name;
                    form.querySelector('[name="description"]').value = mealKit.description;
                    form.querySelector('[name="preparation_price"]').value = mealKit.preparation_price;
                    form.querySelector('[name="cooking_time"]').value = mealKit.cooking_time;
                    form.querySelector('[name="servings"]').value = mealKit.servings;

                    // Populate categories
                    const catSelect = form.querySelector('[name="category_id"]');
                    catSelect.innerHTML = '';
                    if (window.categoriesList) {
                        window.categoriesList.forEach(cat => {
                            const opt = document.createElement('option');
                            opt.value = cat.category_id;
                            opt.textContent = cat.name;
                            if (cat.category_id == mealKit.category_id) opt.selected = true;
                            catSelect.appendChild(opt);
                        });
                    }

                    // Populate ingredients
                    const ingList = form.querySelector('#editIngredientsList');
                    ingList.innerHTML = '';
                    if (window.ingredientsList) {
                        ingredients.forEach(ingredient => {
                            const row = document.createElement('div');
                            row.className = 'ingredient-row';
                            row.style.display = 'contents';
                            row.innerHTML = `
                                <select class="form-select ingredient-select" name="ingredients[]" required style="width: 50%;">
                                    <option value="">Select Ingredient</option>
                                    ${window.ingredientsList.map(ing => `<option value="${ing.ingredient_id}" ${ingredient.ingredient_id == ing.ingredient_id ? 'selected' : ''}>${ing.name}</option>`).join('')}
                                    <option value="new">+ Add New Ingredient</option>
                                </select>
                                <input type="number" class="form-control ingredient-quantity" name="quantities[]" placeholder="Quantity (g)" min="1" value="${ingredient.default_quantity}" required style="width: 50%;">
                                <div class="ingredient-action">
                                    <button type="button" class="btn btn-danger" onclick="this.closest('.ingredient-row').remove(); updateEditIngredientSelectOptions(); updateEditBaseCalories();"><i class="bi bi-trash"></i></button>
                                </div>
                            `;
                            ingList.appendChild(row);
                        });
                        updateEditIngredientSelectOptions();
                        updateEditBaseCalories();
                    }

                    // Add ingredient row handler for edit modal
                    form.querySelector('#addEditIngredientBtn').onclick = function() {
                        const row = document.createElement('div');
                        row.className = 'ingredient-row';
                        row.style.display = 'contents';
                        row.innerHTML = `
                            <select class="form-select ingredient-select" name="ingredients[]" required style="width: 50%;">
                                <option value="">Select Ingredient</option>
                                ${window.ingredientsList.map(ing => `<option value="${ing.ingredient_id}">${ing.name}</option>`).join('')}
                                <option value="new">+ Add New Ingredient</option>
                            </select>
                            <input type="number" class="form-control ingredient-quantity" name="quantities[]" placeholder="Quantity (g)" min="1" required style="width: 50%;">
                            <div class="ingredient-action">
                                <button type="button" class="btn btn-danger" onclick="this.closest('.ingredient-row').remove(); updateEditIngredientSelectOptions(); updateEditBaseCalories();"><i class="bi bi-trash"></i></button>
                            </div>
                        `;
                        ingList.appendChild(row);
                        updateEditIngredientSelectOptions();
                        updateEditBaseCalories();
                        // Add event listeners for dynamic updates
                        const selectEl = row.querySelector('.ingredient-select');
                        selectEl.addEventListener('change', function() {
                            if (this.value === 'new') {
                                showNewIngredientModal(row);
                            } else {
                                updateEditIngredientSelectOptions();
                                updateEditBaseCalories();
                            }
                        });
                        row.querySelector('.ingredient-quantity').addEventListener('input', function() {
                            updateEditBaseCalories();
                        });
                    };

                    // Remove ingredient row handler
                    ingList.addEventListener('click', function(e) {
                        if (e.target.closest('.remove-ingredient-btn')) {
                            e.target.closest('.ingredient-row').remove();
                            updateEditIngredientSelectOptions();
                            updateEditBaseCalories();
                        }
                    });

                    // Ingredient select disables for edit modal
                    function updateEditIngredientSelectOptions() {
                        const selects = ingList.querySelectorAll('.ingredient-select');
                        const selectedIds = Array.from(selects).map(sel => sel.value).filter(val => val && val !== 'new');
                        selects.forEach(select => {
                            const currentVal = select.value;
                            select.innerHTML = '<option value="">Select Ingredient</option>' +
                                window.ingredientsList.map(ing => {
                                    const disabled = selectedIds.includes(String(ing.ingredient_id)) && String(ing.ingredient_id) !== currentVal ? 'disabled' : '';
                                    return `<option value="${ing.ingredient_id}" ${currentVal == ing.ingredient_id ? 'selected' : ''} ${disabled}>${ing.name}</option>`;
                                }).join('') +
                                '<option value="new">+ Add New Ingredient</option>';
                            select.value = currentVal;
                        });
                    }

                    // Auto-calculate and update base calories in edit modal
                    function updateEditBaseCalories() {
                        let totalCalories = 0;
                        ingList.querySelectorAll('.ingredient-row').forEach(row => {
                            const select = row.querySelector('.ingredient-select');
                            const qtyInput = row.querySelector('.ingredient-quantity');
                            const ingId = select.value;
                            const qty = parseFloat(qtyInput.value);
                            if (ingId && ingId !== 'new' && qty && !isNaN(qty)) {
                                const ing = window.ingredientsList.find(i => String(i.ingredient_id) === ingId);
                                if (ing) {
                                    totalCalories += (parseFloat(ing.calories_per_100g) * qty) / 100;
                                }
                            }
                        });
                        const baseCaloriesInput = form.querySelector('[name="base_calories"]');
                        if (baseCaloriesInput) baseCaloriesInput.value = Math.round(totalCalories);
                    }

                    // --- Image input toggle logic ---
                    const toggleInput = form.querySelector('#editToggleImageInput');
                    const urlInputWrapper = form.querySelector('#editImageUrlInputWrapper');
                    const fileInputWrapper = form.querySelector('#editImageFileInputWrapper');
                    const urlInput = form.querySelector('#editImageUrl');
                    const fileInput = form.querySelector('#editImageFile');
                    const previewWrapper = form.querySelector('#editImagePreviewWrapper');
                    const previewImg = form.querySelector('#editImagePreview');

                    // Set initial state: show URL if image_url is present, else show upload
                    if (mealKit.image_url && /^https?:\/\//i.test(mealKit.image_url)) {
                        toggleInput.checked = false;
                        urlInputWrapper.classList.remove('d-none');
                        fileInputWrapper.classList.add('d-none');
                        urlInput.value = mealKit.image_url;
                    } else {
                        toggleInput.checked = true;
                        urlInputWrapper.classList.add('d-none');
                        fileInputWrapper.classList.remove('d-none');
                        urlInput.value = '';
                    }

                    toggleInput.onchange = function() {
                        if (toggleInput.checked) {
                            urlInputWrapper.classList.add('d-none');
                            fileInputWrapper.classList.remove('d-none');
                            urlInput.value = '';
                        } else {
                            urlInputWrapper.classList.remove('d-none');
                            fileInputWrapper.classList.add('d-none');
                            fileInput.value = '';
                        }
                        previewWrapper.style.display = 'none';
                    };

                    // Preview logic
                    urlInput.oninput = function() {
                        if (urlInput.value && /^https?:\/\//i.test(urlInput.value)) {
                            previewImg.src = urlInput.value;
                            previewWrapper.style.display = 'block';
                        } else {
                            previewWrapper.style.display = 'none';
                        }
                    };
                    fileInput.onchange = function() {
                        if (fileInput.files && fileInput.files[0]) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                                previewWrapper.style.display = 'block';
                            };
                            reader.readAsDataURL(fileInput.files[0]);
                        } else {
                            previewWrapper.style.display = 'none';
                        }
                    };

                    // Show the modal
                    const modal = new bootstrap.Modal(modalEl);
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

// Debug: Fill form with random data
$(document).on('click', '#fillDebugDataBtn', function() {
    $('#mealKitName').val('MealKit ' + Math.random().toString(36).substring(2,8));
    $('#categoryId').prop('selectedIndex', 1);
    $('#preparationPrice').val((Math.random()*20+5).toFixed(2));
    $('#cookingTime').val(Math.floor(Math.random()*60)+10);
    $('#servings').val(Math.floor(Math.random()*4)+1);
    $('#mealKitDescription').val('Random description ' + Math.random().toString(36).substring(2,8));
    $('#baseCalories').val(Math.floor(Math.random()*500)+200);
    // Random image url
    $('#imageUrl').val('https://placehold.co/600x600?text=Test');
    $('#toggleImageInput').prop('checked', false).trigger('change');
    // Fill first ingredient row
    let ingSelect = $('#ingredientsList .ingredient-select').first();
    if (ingSelect.length) {
        ingSelect.prop('selectedIndex', 1); // select first non-empty ingredient
        ingSelect.trigger('change');
    }
    let qtyInput = $('#ingredientsList .ingredient-quantity').first();
    if (qtyInput.length) {
        qtyInput.val(Math.floor(Math.random()*200)+50);
    }
});

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