document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Add export buttons to the orders table if it exists
    if (document.getElementById('ordersTable')) {
        // Initialize DataTable with advanced configuration
        const ordersTable = $('#ordersTable').DataTable({
            responsive: true,
            pageLength: 15,
            order: [[2, 'desc']], // Sort by date column (index 2) in descending order
            columnDefs: [
                { orderable: false, targets: [7] }, // Actions column (index 7) is not sortable
                { width: "15%", targets: [1] }, // Customer column
                { width: "12%", targets: [3] }, // Status column
                { width: "10%", targets: [4] }, // Payment column
                { width: "10%", targets: [5] }, // Items column
                { width: "10%", targets: [6] }, // Total column
                { width: "10%", targets: [7] }  // Actions column
            ],
            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search orders...",
                lengthMenu: "_MENU_ orders per page"
            }
        });
        
        // Add export buttons
        new $.fn.dataTable.Buttons(ordersTable, {
            buttons: [
                {
                    extend: 'collection',
                    text: '<i class="bi bi-download"></i> Export',
                    className: 'btn-sm btn-outline-primary ms-2',
                    buttons: [
                        {
                            extend: 'excel',
                            text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                        },
                        {
                            extend: 'print',
                            text: '<i class="bi bi-printer"></i> Print',
                            exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] }
                        }
                    ]
                }
            ]
        });
        
        // Add the buttons to the DataTable
        ordersTable.buttons().container().appendTo($('.dataTables_filter', ordersTable.table().container()));

        // Add payment status filter dropdown
        const filterDropdown = `
        <div class="dropdown d-inline-block ms-2 payment-filter-dropdown">
            <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" id="paymentFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-funnel"></i> Payment Status
            </button>
            <ul class="dropdown-menu" aria-labelledby="paymentFilterDropdown">
                <li><a class="dropdown-item active" href="#" data-filter="all"><i class="bi bi-check2-all"></i> All</a></li>
                <li><a class="dropdown-item" href="#" data-filter="pending"><i class="bi bi-hourglass-split text-warning"></i> Pending</a></li>
                <li><a class="dropdown-item" href="#" data-filter="completed"><i class="bi bi-check-circle-fill text-success"></i> Completed</a></li>
                <li><a class="dropdown-item" href="#" data-filter="failed"><i class="bi bi-x-circle-fill text-danger"></i> Failed</a></li>
                <li><a class="dropdown-item" href="#" data-filter="refunded"><i class="bi bi-arrow-counterclockwise text-info"></i> Refunded</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-filter="needs-verification"><i class="bi bi-shield-exclamation text-warning"></i> Needs Verification</a></li>
            </ul>
        </div>`;
        
        $('.dataTables_filter', ordersTable.table().container()).prepend(filterDropdown);
        
        // Payment status filtering functionality
        $('.payment-filter-dropdown .dropdown-item').on('click', function(e) {
            e.preventDefault();
            
            // Update active state in dropdown
            $('.payment-filter-dropdown .dropdown-item').removeClass('active');
            $(this).addClass('active');
            
            const filter = $(this).data('filter');
            
            // Reset search and apply new filter
            ordersTable.search('').columns().search('').draw();
            
            if (filter === 'all') {
                return; // No filter needed
            }
            
            if (filter === 'needs-verification') {
                // Custom filtering for orders that need verification
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    // Check if payment slip exists but not verified
                    // This looks for exclamation-circle icon in column 4 (Payment)
                    return data[4].includes('exclamation-circle');
                });
                ordersTable.draw();
                $.fn.dataTable.ext.search.pop(); // Remove the custom filter after use
            } else {
                // Filter by payment status text
                let searchText = '';
                switch(filter) {
                    case 'pending': searchText = 'Pending'; break;
                    case 'completed': searchText = 'Completed'; break;
                    case 'failed': searchText = 'Failed'; break;
                    case 'refunded': searchText = 'Refunded'; break;
                }
                
                // Apply filter to Payment column (index 4)
                ordersTable.column(4).search(searchText).draw();
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

        if (newStatus == originalStatus) return;

        updateOrderStatus(orderId, newStatus, originalStatus, this);
    });
    
    // Lightbox for payment slips
    $(document).on('click', '.payment-slip-container', function(e) {
        e.preventDefault();
        const imgSrc = $(this).attr('href');
        
        // Create modal for image lightbox
        const lightboxModal = `
        <div class="modal fade" id="paymentSlipLightbox" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Payment Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center">
                <img src="${imgSrc}" class="img-fluid" alt="Payment Slip" style="max-height: 70vh;">
              </div>
            </div>
          </div>
        </div>`;
        
        // Remove any existing lightbox
        $('#paymentSlipLightbox').remove();
        $('body').append(lightboxModal);
        
        const lightbox = new bootstrap.Modal(document.getElementById('paymentSlipLightbox'));
        lightbox.show();
    });

    // Add event listener for scan transaction button
    $(document).on('click', '#scanTransactionBtn', function() {
        scanTransactionId();
    });
});

// Function to view order details - REMOVED (using dedicated page instead)
// This function has been replaced by direct links to order-details.php

// Function to update order status
function updateOrderStatus(orderId, newStatus, originalStatus, selectElement) {
    if (!confirm('Are you sure you want to change the order status?')) {
        // Reset to original value if canceled
        $(selectElement).val(originalStatus);
        return;
    }

    // Show loading state
    $(selectElement).prop('disabled', true);
    
    // Update status via AJAX
    $.ajax({
        url: '/hm/api/orders/update_status.php',
        type: 'POST',
        data: JSON.stringify({
            order_id: orderId,
            status_id: newStatus
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('success', 'Order status updated successfully');
                $(selectElement).data('original-status', newStatus);
            } else {
                showToast('error', response.message || 'Failed to update order status');
                $(selectElement).val(originalStatus);
            }
        },
        error: function(xhr, status, error) {
            showToast('error', 'An error occurred while updating the order status');
            $(selectElement).val(originalStatus);
            console.error('Error:', error);
        },
        complete: function() {
            $(selectElement).prop('disabled', false);
        }
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
    if (!confirm('Are you sure you want to delete this order? This cannot be undone.')) {
        return;
    }

    $.ajax({
        url: '/hm/api/orders/delete_order.php',
        type: 'POST',
        data: JSON.stringify({
            order_id: orderId
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('success', 'Order deleted successfully');
                // Remove row from table
                $(`tr[data-order-id="${orderId}"]`).fadeOut('slow', function() {
                    $(this).remove();
                });
            } else {
                showToast('error', response.message || 'Failed to delete order');
            }
        },
        error: function(xhr, status, error) {
            showToast('error', 'An error occurred while deleting the order');
            console.error('Error:', error);
        }
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

// Function to verify payment - modified to show verification modal
function verifyPayment(orderId, isResubmitted = false, orderAmount = null) {
    // Clear previous modal data
    document.getElementById('paymentVerificationModal').querySelectorAll('form')[0].reset();
    
    // Set order ID
    document.getElementById('verify_order_id').value = orderId;
    
    // Add hidden field for resubmission flag if it doesn't exist
    if (!document.getElementById('isResubmission')) {
        const resubmissionField = document.createElement('input');
        resubmissionField.type = 'hidden';
        resubmissionField.id = 'isResubmission';
        resubmissionField.name = 'isResubmission';
        document.getElementById('verificationForm').appendChild(resubmissionField);
    }
    
    // Set resubmission flag
    document.getElementById('isResubmission').value = isResubmitted;
    
    // Update modal title based on resubmission status
    const modalTitle = document.getElementById('paymentVerificationModalLabel');
    if (isResubmitted) {
        modalTitle.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Verify Resubmitted Payment';
        
        // Add resubmission badge if not already added
        if (!document.getElementById('resubmissionBadge')) {
            const resubmissionBadge = document.createElement('div');
            resubmissionBadge.id = 'resubmissionBadge';
            resubmissionBadge.className = 'alert alert-info mt-3';
            resubmissionBadge.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-4 me-2"></i>
                    <div>This payment has been resubmitted by the customer after a previous failed verification.</div>
                </div>
            `;
            document.getElementById('paymentSlipPreview').after(resubmissionBadge);
        }
    } else {
        modalTitle.innerHTML = 'Verify Payment';
        
        // Remove resubmission badge if exists
        const resubmissionBadge = document.getElementById('resubmissionBadge');
        if (resubmissionBadge) {
            resubmissionBadge.remove();
        }
    }
    
    // Set amount if provided
    if (orderAmount) {
        document.getElementById('amount_verified').value = orderAmount;
    } else {
        // Try to get amount from the table row data attribute
        const orderRow = document.querySelector(`tr[data-order-id="${orderId}"]`);
        if (orderRow && orderRow.dataset.amount) {
            document.getElementById('amount_verified').value = orderRow.dataset.amount;
        }
    }
    
    // Show modal
    const paymentVerificationModal = new bootstrap.Modal(document.getElementById('paymentVerificationModal'));
    paymentVerificationModal.show();
    
    // Fetch payment details
    fetchPaymentDetails(orderId, isResubmitted);
}

/**
 * Fetch payment details for verification
 */
function fetchPaymentDetails(orderId, isResubmitted) {
    $.ajax({
        url: `/hm/api/orders/get_payment_details.php?order_id=${orderId}&resubmitted=${isResubmitted ? 1 : 0}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const details = response.payment_details;
                
                // Set form fields
                document.getElementById('amount_verified').value = details.order_amount;
                document.getElementById('account_number').value = details.customer_name;
                document.getElementById('company_account').value = details.company_account || '09123456789';
                
                if (details.transaction_id) {
                    document.getElementById('transaction_id').value = details.transaction_id;
                }
                
                // Show payment slip if available
                if (details.transfer_slip_url) {
                    const fileExt = details.transfer_slip_url.split('.').pop().toLowerCase();
                    const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
                    const isPdf = fileExt === 'pdf';
                    
                    let slipHtml = '';
                    
                    if (isImage) {
                        slipHtml = `
                            <h6 class="mb-3">
                                Payment Slip
                                ${isResubmitted ? '<span class="badge bg-primary ms-2">Resubmitted</span>' : ''}
                            </h6>
                            <div class="text-center">
                                <img src="${details.transfer_slip_url}" class="img-fluid rounded shadow-sm" style="max-height: 400px;" alt="Payment Slip">
                            </div>
                            <div class="text-center mt-3">
                                <a href="${details.transfer_slip_url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-fullscreen me-1"></i> View Full Size
                                </a>
                            </div>
                        `;
                    } else if (isPdf) {
                        slipHtml = `
                            <h6 class="mb-3">
                                Payment Slip (PDF)
                                ${isResubmitted ? '<span class="badge bg-primary ms-2">Resubmitted</span>' : ''}
                            </h6>
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="bi bi-file-earmark-pdf fs-1 text-danger"></i>
                                </div>
                                <a href="${details.transfer_slip_url}" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye me-1"></i> View PDF
                                </a>
                            </div>
                        `;
                    } else {
                        slipHtml = `
                            <h6 class="mb-3">
                                Payment Slip
                                ${isResubmitted ? '<span class="badge bg-primary ms-2">Resubmitted</span>' : ''}
                            </h6>
                            <div class="text-center">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    File format not supported for preview
                                </div>
                                <a href="${details.transfer_slip_url}" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="bi bi-download me-1"></i> Download File
                                </a>
                            </div>
                        `;
                    }
                    
                    document.getElementById('paymentSlipPreview').innerHTML = slipHtml;
                } else {
                    document.getElementById('paymentSlipPreview').innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No payment slip available for this order
                        </div>
                    `;
                }

                // If we have verification history, show previous verification attempt details
                if (response.verification_history && response.verification_history.length > 0) {
                    const latestVerification = response.verification_history[0];
                    const verificationHtml = `
                        <div class="mt-3 pt-3 border-top">
                            <h6 class="mb-2"><i class="bi bi-clock-history me-2"></i>Previous Verification</h6>
                            <div class="small text-muted">
                                <p class="mb-1">Last verified on ${latestVerification.created_at_formatted} by ${latestVerification.verified_by_name}</p>
                                <p class="mb-1">Status: <span class="badge bg-${getStatusClass(latestVerification.payment_status)}">${latestVerification.payment_status_text}</span></p>
                                <p class="mb-1">Transaction ID: ${latestVerification.transaction_id || 'Not provided'}</p>
                                <p class="mb-0">Attempt: ${latestVerification.verification_attempt}</p>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('paymentSlipPreview').innerHTML += verificationHtml;
                }
            } else {
                showToast('error', response.message || 'Failed to fetch payment details');
                document.getElementById('paymentSlipPreview').innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle me-2"></i>
                        Error loading payment details: ${response.message || 'Unknown error'}
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            showToast('error', 'An error occurred while fetching payment details');
            console.error('Error:', error);
            document.getElementById('paymentSlipPreview').innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-x-circle me-2"></i>
                    Network error while loading payment details
                </div>
            `;
        }
    });
}

/**
 * Submit payment verification
 */
function submitPaymentVerification() {
    const orderId = $('#verify_order_id').val();
    const paymentStatus = $('#payment_status').val();
    const transactionId = $('#transaction_id').val();
    const verificationNotes = $('#verification_notes').val();
    const amountVerified = $('#amount_verified').val();
    const isResubmission = document.getElementById('isResubmission') ? 
                          document.getElementById('isResubmission').value === 'true' : false;
    
    if (!transactionId.trim()) {
        showToast('error', 'Please enter a valid transaction ID');
        $('#transaction_id').focus();
        return;
    }
    
    // Disable submit button and show loading
    $('#paymentVerificationModal .modal-footer button').prop('disabled', true);
    $('#paymentVerificationModal .modal-footer button:last-child').html(
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...'
    );
    
    const verificationData = {
        order_id: orderId,
        verify: true,
        verification_details: {
            transaction_id: transactionId,
            verification_notes: verificationNotes,
            payment_status: paymentStatus,
            amount_verified: amountVerified || 0,
            is_resubmission: isResubmission
        }
    };
    
    fetch('/hm/api/orders/verify_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(verificationData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Payment verification completed successfully');
            
            // Hide modal
            bootstrap.Modal.getInstance(document.getElementById('paymentVerificationModal')).hide();
            
            // Store success message for after reload
            localStorage.setItem('orderMessage', data.message || 'Payment verification completed successfully');
            localStorage.setItem('orderMessageType', 'success');
            
            // Reload page after a short delay
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            throw new Error(data.message || 'Failed to verify payment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', error.message || 'An error occurred during verification');
        
        // Re-enable buttons
        $('#paymentVerificationModal .modal-footer button').prop('disabled', false);
        $('#paymentVerificationModal .modal-footer button:last-child').html(
            '<i class="bi bi-shield-check me-2"></i>Verify Payment'
        );
    });
}

// Function to scan payment slip for transaction ID using OCR
function scanTransactionId() {
    // Get the payment slip image source
    const paymentSlipImg = document.querySelector('#paymentSlipPreview img');
    if (!paymentSlipImg) {
        showToast('error', 'No payment slip image found');
        return;
    }
    
    const imgSrc = paymentSlipImg.src;
    const statusElement = document.getElementById('transaction_scan_status');
    
    // Update status to show scanning is in progress
    statusElement.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Scanning image for transaction ID...</span>';
    
    // Simple OCR using Tesseract.js (client-side OCR)
    // First, check if the Tesseract script is loaded
    if (typeof Tesseract === 'undefined') {
        // Load Tesseract.js dynamically
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js';
        script.onload = function() {
            performOCR(imgSrc, statusElement);
        };
        script.onerror = function() {
            statusElement.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Failed to load OCR library</span>';
        };
        document.head.appendChild(script);
    } else {
        performOCR(imgSrc, statusElement);
    }
}

// Function to perform OCR on the image
function performOCR(imgSrc, statusElement) {
    // Set up OCR worker
    Tesseract.recognize(
        imgSrc,
        'eng',
        { logger: m => console.log(m) }
    ).then(({ data: { text } }) => {
        console.log("OCR Result:", text);
        
        // Extract potential transaction ID using common patterns
        const transactionId = extractTransactionId(text);
        const accountNumber = extractAccountNumber(text);
        
        let resultMessage = '';
        
        if (transactionId) {
            document.getElementById('transaction_id').value = transactionId;
            resultMessage += '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Transaction ID found: ' + transactionId + '</span>';
        } else {
            resultMessage += '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> No transaction ID found.</span>';
        }
        
        if (accountNumber) {
            resultMessage += '<br><span class="text-success"><i class="bi bi-check-circle-fill"></i> Account number found: ' + accountNumber + '</span>';
            
            // If no transaction ID was found, we can use the account number as a fallback
            if (!transactionId) {
                document.getElementById('transaction_id').value = 'ACC-' + accountNumber;
            }
            
            // Add account number to notes
            const notes = document.getElementById('verification_notes');
            if (notes.value) {
                notes.value += '\nAccount: ' + accountNumber;
            } else {
                notes.value = 'Account: ' + accountNumber;
            }
        }
        
        if (!transactionId && !accountNumber) {
            resultMessage = '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> No transaction ID or account number found. Please enter manually.</span>';
        }
        
        statusElement.innerHTML = resultMessage;
    }).catch(err => {
        console.error('OCR Error:', err);
        statusElement.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> OCR processing failed</span>';
    });
}

// Helper function to extract transaction ID from OCR text
function extractTransactionId(text) {
    // Common patterns for transaction IDs
    const patterns = [
        /transaction\s*(?:id|no|number|#)\s*[:. ]?\s*([a-z0-9_\-]{5,})/i,
        /(?:ref|reference)\s*(?:id|no|number|#)\s*[:. ]?\s*([a-z0-9_\-]{5,})/i,
        /(?:payment|transfer)\s*(?:id|no|number|#)\s*[:. ]?\s*([a-z0-9_\-]{5,})/i,
        /(?:tx|txn|txid)\s*[:. ]?\s*([a-z0-9_\-]{5,})/i,
        /([a-z0-9]{8,})/i  // Fall back to any alphanumeric string of 8+ chars
    ];
    
    // Try each pattern in order
    for (const pattern of patterns) {
        const match = text.match(pattern);
        if (match && match[1]) {
            return match[1].trim();
        }
    }
    
    return null;
}

// Helper function to extract account number from OCR text
function extractAccountNumber(text) {
    // Common patterns for account numbers
    const patterns = [
        /account\s*(?:id|no|number|#)\s*[:. ]?\s*(\d{4,})/i,
        /acc\s*(?:id|no|number|#|\.)\s*[:. ]?\s*(\d{4,})/i,
        /(?:to|recipient)\s*(?:account|acc)\s*[:. ]?\s*(\d{4,})/i,
        /account\s*:\s*(\d{4,})/i,
        /acc\s*:\s*(\d{4,})/i
    ];
    
    // Try each pattern in order
    for (const pattern of patterns) {
        const match = text.match(pattern);
        if (match && match[1]) {
            return match[1].trim();
        }
    }
    
    return null;
}

// Function to verify payment with details - legacy function for order details page
function verifyPaymentWithDetails(orderId) {
    // Redirect to the order details page
    window.location.href = `/hm/admin/order-details.php?id=${orderId}`;
}

// Function to show payment verification history
function showPaymentHistory(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('paymentHistoryModal'));
    
    // Reset content
    document.getElementById('paymentHistoryContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading payment history...</p>
        </div>
    `;
    
    // Show the modal
    modal.show();
    
    // Fetch payment history
    $.ajax({
        url: `/hm/api/orders/get_payment_history.php?order_id=${orderId}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderPaymentHistory(response);
            } else {
                showToast('error', response.message || 'Failed to fetch payment history');
                document.getElementById('paymentHistoryContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle me-2"></i>
                        Error loading payment history: ${response.message || 'Unknown error'}
                    </div>
                `;
            }
        },
        error: function(xhr, status, error) {
            showToast('error', 'An error occurred while fetching payment history');
            console.error('Error:', error);
            document.getElementById('paymentHistoryContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle me-2"></i>
                    Network error while loading payment history
                </div>
            `;
        }
    });
}

/**
 * Render payment history in the modal
 */
function renderPaymentHistory(data) {
    const order = data.order;
    const paymentHistory = data.payment_history;
    const verificationLogs = data.verification_logs;
    
    let html = `
        <div class="mb-4">
            <h5 class="mb-3">Order Information</h5>
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order ID:</strong> #${order.order_id}</p>
                            <p class="mb-1"><strong>Customer:</strong> ${order.customer_name}</p>
                            <p class="mb-1"><strong>Date:</strong> ${order.created_at_formatted}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Payment Method:</strong> ${order.payment_method}</p>
                            <p class="mb-1"><strong>Account:</strong> ${order.payment_account || 'N/A'}</p>
                            <p class="mb-1"><strong>Amount:</strong> ${Number(order.total_amount).toLocaleString()} MMK</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Payment and verification history
    if (paymentHistory && paymentHistory.length > 0) {
        html += `
            <div class="mb-4">
                <h5 class="mb-3">Payment Verification History</h5>
                <div class="card mb-3">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered m-0">
                                <thead>
                                    <tr class="table-light">
                                        <th>Date</th>
                                        <th>Attempt</th>
                                        <th>Verified By</th>
                                        <th>Transaction ID</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Transfer Slip</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;
        
        paymentHistory.forEach(item => {
            const statusClass = getStatusClass(item.verification_status || item.payment_status);
            
            // Format the slip URL for viewing
            let slipHtml = '';
            if (item.transfer_slip_url) {
                slipHtml = `
                    <a href="${item.transfer_slip_url}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-image me-1"></i> View
                    </a>
                `;
            } else {
                slipHtml = '<span class="text-muted">None</span>';
            }
            
            html += `
                <tr>
                    <td>${item.verification_created_at_formatted || item.payment_created_at_formatted}</td>
                    <td>${item.verification_attempt || '1'}</td>
                    <td>${item.verified_by_name || 'Not verified'}</td>
                    <td>${item.verification_transaction_id || item.transaction_id || 'N/A'}</td>
                    <td>${item.amount_verified ? Number(item.amount_verified).toLocaleString() + ' MMK' : 'N/A'}</td>
                    <td><span class="badge bg-${statusClass}">${item.verification_status_text || item.payment_status_text}</span></td>
                    <td>${slipHtml}</td>
                </tr>
            `;
        });
        
        html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        html += `
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle me-2"></i>
                No payment verifications found for this order
            </div>
        `;
    }
    
    // Verification logs
    if (verificationLogs && verificationLogs.length > 0) {
        html += `
            <div>
                <h5 class="mb-3">Verification Activity Log</h5>
                <div class="timeline">
        `;
        
        verificationLogs.forEach((log, index) => {
            const fromStatusClass = getStatusClass(log.status_changed_from);
            const toStatusClass = getStatusClass(log.status_changed_to);
            
            html += `
                <div class="timeline-item">
                    <div class="timeline-dot bg-${toStatusClass}"></div>
                    <div class="timeline-content">
                        <div class="card">
                            <div class="card-header bg-light py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>${log.created_at_formatted}</span>
                                    <div>
                                        <span class="badge bg-info me-2">Verification #${log.verification_id}</span>
                                        <span class="badge bg-secondary">Log #${log.log_id}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Status Changed:</strong> 
                                    <span class="badge bg-${fromStatusClass} me-1">${log.status_from_text}</span>
                                    <i class="bi bi-arrow-right mx-1"></i>
                                    <span class="badge bg-${toStatusClass}">${log.status_to_text}</span>
                                </p>
                                <p class="mb-2"><strong>Amount:</strong> ${Number(log.amount).toLocaleString()} MMK</p>
                                <p class="mb-2"><strong>Verified By:</strong> ${log.verified_by_name}</p>
                                ${log.admin_notes ? `<p class="mb-0"><strong>Notes:</strong> ${log.admin_notes}</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    document.getElementById('paymentHistoryContent').innerHTML = html;
}

/**
 * Get appropriate Bootstrap color class for payment status
 */
function getStatusClass(status) {
    switch (parseInt(status)) {
        case 0: return 'warning';   // Pending
        case 1: return 'success';   // Completed
        case 2: return 'danger';    // Failed
        case 3: return 'info';      // Refunded
        case 4: return 'warning';   // Partial
        default: return 'secondary';
    }
}

/**
 * Copy account number to clipboard
 */
function copyAccountNumber() {
    const companyAccount = document.getElementById('company_account');
    
    if (companyAccount && companyAccount.value) {
        navigator.clipboard.writeText(companyAccount.value).then(function() {
            showToast('success', 'Account number copied to clipboard');
        }, function() {
            showToast('error', 'Failed to copy account number');
        });
    }
}

/**
 * Show toast message
 */
function showToast(type, message) {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    const toastId = 'toast-' + Date.now();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center border-0 text-white bg-${type === 'success' ? 'success' : 'danger'} mb-2`;
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const toastInstance = new bootstrap.Toast(toast, { delay: 3000 });
    toastInstance.show();
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

/**
 * Create toast container if it doesn't exist
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '1050';
    document.body.appendChild(container);
    return container;
}

// Add CSS for timeline in payment history
const style = document.createElement('style');
style.innerHTML = `
.timeline {
    position: relative;
    padding: 0;
    list-style: none;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 20px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
    padding-left: 45px;
}

.timeline-dot {
    position: absolute;
    left: 16px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    top: 15px;
    z-index: 1;
}

.timeline-content {
    position: relative;
}
`;
document.head.appendChild(style);