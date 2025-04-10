document.addEventListener('DOMContentLoaded', function() {
    // Destroy existing DataTable instance if it exists
    if ($.fn.DataTable.isDataTable('#ordersTable')) {
        $('#ordersTable').DataTable().destroy();
    }

    // Initialize DataTable
    const ordersTable = $('#ordersTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'collection',
                text: '<i class="bi bi-download"></i> Export',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="bi bi-printer"></i> Print',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5]
                        }
                    }
                ]
            }
        ],
        order: [[2, 'desc']], // Sort by date by default
        pageLength: 25,
        language: {
            search: "Search orders:",
            lengthMenu: "Show _MENU_ orders per page",
            info: "Showing _START_ to _END_ of _TOTAL_ orders",
            infoEmpty: "No orders found",
            emptyTable: "No orders available"
        }
    });

    // Handle status changes
    $('.status-select').on('change', function() {
        const orderId = $(this).data('order-id');
        const originalStatus = $(this).data('original-status');
        const newStatus = $(this).val();
        
        if (confirm('Are you sure you want to update this order\'s status?')) {
            updateOrderStatus(orderId, newStatus, this);
        } else {
            $(this).val(originalStatus); // Reset to original value if cancelled
        }
    });
});

// Function to view order details
function viewOrderDetails(orderId) {
    fetch(`/hm/admin/api/orders/get_details.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('orderDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                modal.show();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'An error occurred while fetching order details');
            console.error('Error:', error);
        });
}

// Function to update order status
function updateOrderStatus(orderId, statusId, selectElement) {
    fetch('/hm/admin/api/orders/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: orderId,
            status_id: statusId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            $(selectElement).data('original-status', statusId);
        } else {
            showAlert('error', data.message);
            $(selectElement).val($(selectElement).data('original-status'));
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred while updating the order status');
        console.error('Error:', error);
        $(selectElement).val($(selectElement).data('original-status'));
    });
}

// Function to delete order
function deleteOrder(orderId) {
    if (confirm('Are you sure you want to delete this order? This action cannot be undone.')) {
        fetch('/hm/admin/api/orders/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                $('#ordersTable').DataTable().row($(`button[onclick="deleteOrder(${orderId})"]`).closest('tr')).remove().draw();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            showAlert('error', 'An error occurred while deleting the order');
            console.error('Error:', error);
        });
    }
}

// Function to show alerts
function showAlert(type, message) {
    const alertDiv = document.getElementById('orderMessage');
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