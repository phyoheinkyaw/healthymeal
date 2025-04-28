function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const body = document.body;
    
    if (!sidebar) {
        return;
    }
    
    if (sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
        body.classList.remove('sidebar-open');
    } else {
        sidebar.classList.add('show');
        body.classList.add('sidebar-open');
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const body = document.body;
    
    if (!sidebar || !sidebarToggle) {
        return;
    }
    
    if (window.innerWidth <= 991.98) {
        if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                body.classList.remove('sidebar-open');
            }
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('adminSidebar');
    const body = document.body;
    
    if (!sidebar) {
        return;
    }
    
    if (window.innerWidth > 991.98) {
        sidebar.classList.remove('show');
        body.classList.remove('sidebar-open');
    }
});

// Helper function to show alerts (moved from meal-kits.js for global use)
function showAlert(type, message) {
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
        alert('Error showing alert');
    }
}

// Custom confirm modal for delete (dynamic, orders/categories style)
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

// Initialize DataTables
$(document).ready(function() {
    if (!$.fn.DataTable.isDataTable('#ordersTable')) {
        $('#ordersTable').DataTable({
            responsive: true,
            order: [[2, 'desc']], // Sort by date column by default
            language: {
                search: "Search orders:",
                lengthMenu: "Show _MENU_ orders per page",
                info: "Showing _START_ to _END_ of _TOTAL_ orders",
                emptyTable: "No orders available"
            }
        });
    }
});