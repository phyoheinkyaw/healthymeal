document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for add category form
    document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveCategory();
    });

    // Add event listener for edit category form
    document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateCategory();
    });

    // Show alert if present in localStorage (after reload)
    const storedMsg = localStorage.getItem('categoryMessage');
    const storedType = localStorage.getItem('categoryMessageType');
    if (storedMsg && storedType) {
        showAlert(storedType, storedMsg);
        localStorage.removeItem('categoryMessage');
        localStorage.removeItem('categoryMessageType');
    }
});

// Function to save new category
function saveCategory() {
    const form = document.getElementById('addCategoryForm');
    const formData = new FormData(form);

    fetch('/hm/admin/api/categories/save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store message for after reload
            localStorage.setItem('categoryMessage', data.message);
            localStorage.setItem('categoryMessageType', 'success');
            $('#addCategoryModal').modal('hide');
            location.reload();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred while saving the category');
        console.error('Error:', error);
    });
}

// Function to get category details for editing
function editCategory(categoryId) {
    fetch(`/hm/admin/api/categories/get.php?id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const form = document.getElementById('editCategoryForm');
                form.elements['category_id'].value = data.category.category_id;
                form.elements['name'].value = data.category.name;
                form.elements['description'].value = data.category.description;
                $('#editCategoryModal').modal('show');
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'An error occurred while fetching category details');
            console.error('Error:', error);
        });
}

// Function to update category
function updateCategory() {
    const form = document.getElementById('editCategoryForm');
    const formData = new FormData(form);

    fetch('/hm/admin/api/categories/update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store message for after reload
            localStorage.setItem('categoryMessage', data.message);
            localStorage.setItem('categoryMessageType', 'success');
            $('#editCategoryModal').modal('hide');
            location.reload();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred while updating the category');
        console.error('Error:', error);
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

function deleteCategory(categoryId) {
    showDeleteConfirmModal(function() {
        fetch('/hm/admin/api/categories/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ category_id: categoryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                localStorage.setItem('categoryMessage', data.message);
                localStorage.setItem('categoryMessageType', 'success');
                location.reload();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'An error occurred while deleting the category');
            console.error('Error:', error);
        });
    }, {
        title: 'Delete Category',
        message: 'Are you sure you want to delete this category? This action cannot be undone.',
        icon: '<i class="bi bi-trash-fill text-danger me-2"></i>'
    });
}

// Function to show alerts
function showAlert(type, message) {
    const alertDiv = document.getElementById('categoryMessage');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    
    alertDiv.innerHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = alertDiv.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}