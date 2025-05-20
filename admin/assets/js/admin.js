document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar functionality
    initSidebar();
    
    // Initialize DataTables with consistent settings
    initDataTables();
    
    // Auto-dismiss alerts
    autoCloseAlerts();
});

// Sidebar functionality
function initSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');
    const closeSidebarBtn = document.querySelector('.btn-close-sidebar');
    
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', function() {
            toggleSidebar();
        });
    }
    
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            closeSidebar();
        });
    }
    
    // Toggle mini sidebar on medium screens
    if (window.innerWidth >= 992 && window.innerWidth < 1200) {
        document.querySelector('.admin-container').classList.add('mini-sidebar');
    }
    
    // Handle window resize events
    window.addEventListener('resize', function() {
        handleResponsiveLayout();
    });
    
    // Initial layout setup
    handleResponsiveLayout();
}

function handleResponsiveLayout() {
    const adminContainer = document.querySelector('.admin-container');
    
    // Toggle mini sidebar based on screen width
    if (window.innerWidth >= 992 && window.innerWidth < 1200) {
        adminContainer.classList.add('mini-sidebar');
    } else {
        adminContainer.classList.remove('mini-sidebar');
    }
    
    // Close sidebar on mobile view when resizing
    if (window.innerWidth < 992) {
        closeSidebar();
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) return;
    
    if (sidebar.classList.contains('show')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

function openSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) return;
    
    sidebar.classList.add('show');
    if (sidebarOverlay) {
        sidebarOverlay.classList.add('show');
    }
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) return;
    
    sidebar.classList.remove('show');
    if (sidebarOverlay) {
        sidebarOverlay.classList.remove('show');
    }
    document.body.style.overflow = '';
}

// Initialize DataTables
function initDataTables() {
    // Select all DataTables in the admin panel
    const tables = document.querySelectorAll('table.dataTable, table.table');
    
    // Initialize DataTables with consistent settings
    tables.forEach(table => {
        // Skip tables that are initialized in specific page scripts
        const skipTables = [
            'blogPostsTable',    // Initialized in blog-posts.js
            'ordersTable',       // Initialized in orders.js
            'usersTable',        // Initialized in users.js
            'ingredientsTable',  // Initialized in ingredients.js
            'categoriesTable',   // Initialized in categories.js
            'mealKitsTable'      // Initialized in meal-kits.js
        ];
        
        if (table.id && !table.classList.contains('dataTable') && !skipTables.includes(table.id)) {
            try {
                const dataTable = new DataTable('#' + table.id, {
                    responsive: true,
                    order: [[0, 'desc']], // Default sorting by ID descending
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        emptyTable: "No data available",
                        paginate: {
                            first: '<i class="bi bi-chevron-double-left"></i>',
                            previous: '<i class="bi bi-chevron-left"></i>',
                            next: '<i class="bi bi-chevron-right"></i>',
                            last: '<i class="bi bi-chevron-double-right"></i>'
                        }
                    },
                    initComplete: function() {
                        // Add placeholder to search input
                        $('.dataTables_filter input')
                            .attr('placeholder', 'Type to search...')
                            .addClass('search-input');
                    }
                });
            } catch (e) {
                console.warn('Could not initialize DataTable for #' + table.id, e);
            }
        }
    });
}

// Auto-dismiss alerts
function autoCloseAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.persistent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert && alert.parentNode) {
                alert.classList.add('fade-out');
                setTimeout(() => {
                    if (alert.parentNode) alert.parentNode.removeChild(alert);
                }, 500);
            }
        }, 5000);
    });
}

// Show alert function for JS-based alerts
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.getElementById('alertContainer') || document.createElement('div');
    
    if (!document.getElementById('alertContainer')) {
        alertContainer.id = 'alertContainer';
        alertContainer.className = 'position-fixed top-0 end-0 p-3 z-index-1061';
        alertContainer.style.zIndex = '1061';
        document.body.appendChild(alertContainer);
    }
    
    const alertId = 'alert-' + Date.now();
    const alertHTML = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHTML);
    
    const alertElement = document.getElementById(alertId);
    
    if (duration > 0) {
        setTimeout(() => {
            if (alertElement && alertElement.parentNode) {
                alertElement.classList.add('fade-out');
                setTimeout(() => {
                    if (alertElement.parentNode) alertElement.parentNode.removeChild(alertElement);
                }, 500);
            }
        }, duration);
    }
    
    return alertElement;
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