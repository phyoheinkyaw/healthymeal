document.addEventListener('DOMContentLoaded', function() {
    // Only initialize DataTable if not already initialized
    if (!$.fn.DataTable.isDataTable('#ordersTable')) {
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
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="bi bi-printer"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
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
    }

    // Show alert from localStorage if present (after reload)
    const storedMsg = localStorage.getItem('orderMessage');
    const storedType = localStorage.getItem('orderMessageType');
    if (storedMsg && storedType) {
        showAlert(storedType, storedMsg);
        localStorage.removeItem('orderMessage');
        localStorage.removeItem('orderMessageType');
    }

    // Handle status changes
    $('.status-select').on('change', function() {
        const orderId = $(this).data('order-id');
        const originalStatus = $(this).data('original-status');
        const newStatus = $(this).val();

        // Custom Bootstrap modal confirm (replaces window.confirm)
        showOrderStatusConfirm(() => {
            updateOrderStatus(orderId, newStatus, this);
        }, () => {
            $(this).val(originalStatus); // Reset to original value if cancelled
        });
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
            // Store message for after reload
            localStorage.setItem('orderMessage', data.message);
            localStorage.setItem('orderMessageType', 'success');
            location.reload();
        } else {
            localStorage.setItem('orderMessage', data.message);
            localStorage.setItem('orderMessageType', 'error');
            location.reload();
        }
    })
    .catch(error => {
        showAlert('error', 'An error occurred while updating the order status');
        console.error('Error:', error);
        $(selectElement).val($(selectElement).data('original-status'));
    });
}

// Custom confirm modal for order status change
function showOrderStatusConfirm(onConfirm, onCancel) {
    // Remove any existing modal
    $('#orderStatusConfirmModal').remove();
    const modalHtml = `
    <div class="modal fade" id="orderStatusConfirmModal" tabindex="-1" aria-labelledby="orderStatusConfirmLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-warning-subtle">
            <h5 class="modal-title" id="orderStatusConfirmLabel"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Confirm Status Change</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to update this order's status?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="orderStatusConfirmBtn">Yes, Update</button>
          </div>
        </div>
      </div>
    </div>`;
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('orderStatusConfirmModal'));
    modal.show();
    $('#orderStatusConfirmBtn').on('click', function() {
        modal.hide();
        if (onConfirm) onConfirm();
    });
    $('#orderStatusConfirmModal').on('hidden.bs.modal', function() {
        if (onCancel) onCancel();
        $('#orderStatusConfirmModal').remove();
    });
}

// Function to delete order
function deleteOrder(orderId) {
    showOrderDeleteConfirm(() => {
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
                localStorage.setItem('orderMessage', data.message);
                localStorage.setItem('orderMessageType', 'success');
                location.reload();
            } else {
                localStorage.setItem('orderMessage', data.message);
                localStorage.setItem('orderMessageType', 'error');
                location.reload();
            }
        })
        .catch(error => {
            localStorage.setItem('orderMessage', 'An error occurred while deleting the order');
            localStorage.setItem('orderMessageType', 'error');
            location.reload();
        });
    });
}

// Custom confirm modal for order delete
function showOrderDeleteConfirm(onConfirm) {
    $('#orderDeleteConfirmModal').remove();
    const modalHtml = `
    <div class="modal fade" id="orderDeleteConfirmModal" tabindex="-1" aria-labelledby="orderDeleteConfirmLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger-subtle">
            <h5 class="modal-title" id="orderDeleteConfirmLabel"><i class="bi bi-trash-fill text-danger me-2"></i>Delete Order</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to delete this order? This action cannot be undone.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="orderDeleteConfirmBtn">Yes, Delete</button>
          </div>
        </div>
      </div>
    </div>`;
    $('body').append(modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('orderDeleteConfirmModal'));
    modal.show();
    $('#orderDeleteConfirmBtn').on('click', function() {
        modal.hide();
        if (onConfirm) onConfirm();
    });
    $('#orderDeleteConfirmModal').on('hidden.bs.modal', function() {
        $('#orderDeleteConfirmModal').remove();
    });
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