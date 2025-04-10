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

// Initialize DataTables
$(document).ready(function() {
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
});