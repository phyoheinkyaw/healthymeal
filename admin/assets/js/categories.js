document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const categoriesTable = $('#categoriesTable').DataTable({
        order: [[1, 'asc']], // Sort by name by default
        columnDefs: [
            { orderable: false, targets: -1 } // Disable sorting on actions column
        ],
        pageLength: 10,
        language: {
            search: "Search categories:",
            lengthMenu: "Show _MENU_ categories per page",
            info: "Showing _START_ to _END_ of _TOTAL_ categories",
            infoEmpty: "No categories found",
            emptyTable: "No categories available"
        }
    });

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
            showAlert('success', data.message);
            $('#addCategoryModal').modal('hide');
            location.reload(); // Reload to show new category
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
            showAlert('success', data.message);
            $('#editCategoryModal').modal('hide');
            location.reload(); // Reload to show updated category
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred while updating the category');
        console.error('Error:', error);
    });
}

// Function to delete category
function deleteCategory(categoryId) {
    if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
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
                showAlert('success', data.message);
                location.reload(); // Reload to remove deleted category
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'An error occurred while deleting the category');
            console.error('Error:', error);
        });
    }
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