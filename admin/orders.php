<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role != 1) {
    header("Location: /hm/login.php");
    exit();
}

// Check for payment resubmissions from session
$resubmitted_order_id = 0;
if (isset($_SESSION['payment_resubmitted'])) {
    $resubmitted_order_id = $_SESSION['payment_resubmitted'];
    // Clear the flag after use
    unset($_SESSION['payment_resubmitted']);
}

// Fetch all orders with detailed information
$stmt = $mysqli->prepare("
    SELECT 
        o.*,
        os.status_name,
        u.full_name as customer_name,
        u.email as customer_email,
        ph.payment_id,
        COALESCE(pv.payment_status, 0) as payment_status,
        COALESCE(pv.payment_verified, 0) as payment_verified,
        COALESCE(pv.verification_attempt, 0) as verification_attempt,
        COALESCE(pv.resubmission_status, 0) as resubmission_status,
        ph.transaction_id,
        ps.payment_method,
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
        pv.transfer_slip
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_status os ON o.status_id = os.status_id
    LEFT JOIN (
        SELECT ph1.* 
        FROM payment_history ph1
        LEFT JOIN payment_history ph2 ON ph1.order_id = ph2.order_id AND ph1.payment_id < ph2.payment_id
        WHERE ph2.payment_id IS NULL
    ) ph ON o.order_id = ph.order_id
    LEFT JOIN (
        SELECT pv1.*
        FROM payment_verifications pv1
        LEFT JOIN payment_verifications pv2
            ON pv1.order_id = pv2.order_id
            AND (pv1.created_at < pv2.created_at OR (pv1.created_at = pv2.created_at AND pv1.verification_id < pv2.verification_id))
        WHERE pv2.verification_id IS NULL
    ) pv ON o.order_id = pv.order_id
    LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
    ORDER BY o.created_at DESC
");

$stmt->execute();
$orders = $stmt->get_result();

// Fetch order statuses for dropdown
$statuses = [];
$status_result = $mysqli->query("SELECT * FROM order_status ORDER BY status_id");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $statuses[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<div class="overlay" onclick="toggleSidebar()"></div>
<div class="admin-container">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="sidebar-toggle">
        <button class="btn btn-dark" type="button" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
    </div>
    
    <main class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="page-title">Orders Management</h3>
                </div>
            </div>

            <!-- Alert for messages -->
            <div id="orderMessage"></div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm" id="ordersTable">
                            <thead>
                                <tr>
                                    <th><small>ID</small></th>
                                    <th><small>Customer</small></th>
                                    <th><small>Created</small></th>
                                    <th style="min-width: 130px; max-width: 160px;"><small>Status</small></th>
                                    <th><small>Payment</small></th>
                                    <th><small>Qty</small></th>
                                    <th><small>Total</small></th>
                                    <th><small>Actions</small></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = $orders->fetch_assoc()): 
                                    $is_resubmitted = ($resubmitted_order_id > 0 && $resubmitted_order_id == $order['order_id']);
                                ?>
                                <tr data-order-id="<?php echo $order['order_id']; ?>" 
                                    data-status-id="<?php echo $order['status_id']; ?>"
                                    data-status="<?php echo htmlspecialchars($order['status_name']); ?>"
                                    data-amount="<?php echo $order['total_amount']; ?>"
                                    <?php if ($is_resubmitted): ?>class="table-warning" data-resubmitted="true"<?php elseif ($order['resubmission_status'] == 2): ?>class="table-warning" data-needs-resubmit="true"<?php endif; ?>>
                                    <td><small>#<?php echo $order['order_id']; ?></small></td>
                                    <td>
                                        <div class="small"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td>
                                        <div class="small"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                        <small class="text-muted d-none d-md-block"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                    </td>
                                    <td style="min-width: 130px; max-width: 160px;">
                                        <select class="form-select form-select-sm status-select" 
                                                data-order-id="<?php echo $order['order_id']; ?>"
                                                data-original-status="<?php echo $order['status_id']; ?>">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?php echo $status['status_id']; ?>" 
                                                        <?php echo ($status['status_id'] == $order['status_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status['status_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <?php
                                            $paymentStatusClass = match($order['payment_status']) {
                                                0 => 'warning',
                                                1 => 'success',
                                                2 => 'danger',
                                                3 => 'info',
                                                4 => 'warning',
                                                default => 'secondary'
                                            };
                                            $paymentStatusText = match($order['payment_status']) {
                                                0 => 'Pending',
                                                1 => 'Completed',
                                                2 => 'Failed',
                                                3 => 'Refunded',
                                                4 => 'Partial',
                                                default => 'Unknown'
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $paymentStatusClass; ?> mb-1">
                                                <?php echo $paymentStatusText; ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($order['payment_method']); ?>
                                                <?php if(!empty($order['transfer_slip'])): ?>
                                                    <i class="bi bi-<?php echo $order['payment_verified'] == 1 ? 'check-circle-fill text-success' : 'exclamation-circle text-warning'; ?>"></i>
                                                    <?php if($order['verification_attempt'] > 1): ?>
                                                        <span class="badge bg-info">Attempt <?php echo $order['verification_attempt']; ?></span>
                                                    <?php endif; ?>
                                                    <?php if($order['resubmission_status'] == 1): ?>
                                                        <span class="badge bg-primary">Resubmitted</span>
                                                    <?php elseif($order['resubmission_status'] == 2): ?>
                                                        <span class="badge bg-warning text-dark">Needs Resubmit</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if($order['payment_method'] === 'Cash on Delivery' && $order['payment_verified'] == 0): ?>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-success w-100" 
                                                        onclick="verifyCODPayment(<?php echo $order['order_id']; ?>)"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Mark COD as Verified">
                                                    <i class="bi bi-cash-coin"></i> <span class="d-none d-lg-inline">Verify COD</span>
                                                </button>
                                            </div>
                                            <?php elseif(!empty($order['transfer_slip']) && ($order['payment_verified'] == 0 || $order['payment_status'] == 2)): ?>
                                            <div class="mt-1">
                                                <?php
                                                // Allow verification only if:
                                                // 1. Not verified yet ($order['payment_verified'] == 0) AND payment status is not failed (status 2)
                                                // OR 2. This is a proper resubmission (tracked via resubmitted flag or resubmission_status)
                                                // OR 3. Payment has failed but resubmission is provided (resubmission_status == 1)
                                                $isResubmission = $is_resubmitted || $order['resubmission_status'] == 1;
                                                $canVerify = ($order['payment_verified'] == 0 && $order['payment_status'] != 2) || $isResubmission;
                                                
                                                if ($canVerify): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success w-100" 
                                                        onclick="verifyPayment(<?php echo $order['order_id']; ?>, <?php echo $isResubmission ? 'true' : 'false'; ?>)"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo $isResubmission ? 'Verify Resubmitted Payment' : 'Verify Payment'; ?>">
                                                    <i class="bi bi-shield-check"></i> <span class="d-none d-lg-inline"><?php echo $isResubmission ? 'Verify Resubmit' : 'Verify'; ?></span>
                                                </button>
                                                <?php else: ?>
                                                <span class="badge bg-secondary w-100" data-bs-toggle="tooltip" title="Payment marked as failed. User must resubmit payment.">
                                                    <i class="bi bi-x-circle"></i> <span class="d-none d-lg-inline">Awaiting Resubmit</span>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small"><?php echo $order['items_count']; ?> items</div>
                                    </td>
                                    <td>
                                        <strong class="small"><?php echo number_format($order['total_amount']); ?> MMK</strong>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="order-details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if(!empty($order['transfer_slip'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                    onclick="showPaymentHistory(<?php echo $order['order_id']; ?>)"
                                                    data-bs-toggle="tooltip" title="Payment History">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Payment Verification Modal -->
<div class="modal fade" id="paymentVerificationModal" tabindex="-1" aria-labelledby="paymentVerificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 0.75rem; overflow: hidden;">
            <div class="modal-header border-0 p-4" style="background: linear-gradient(135deg, #0093E9 0%, #80D0C7 100%);">
                <div class="d-flex align-items-center">
                    <div class="bg-white p-2 rounded-circle shadow me-3">
                        <i class="bi bi-shield-check fs-4 text-primary"></i>
                    </div>
                    <h5 class="modal-title fs-4 fw-bold text-white m-0" id="paymentVerificationModalLabel">Verify Payment</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="paymentSlipPreview" class="text-center mb-4 p-3 bg-light rounded-4 shadow-sm">
                    <!-- Payment slip image will be shown here -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading payment details...</p>
                    </div>
                </div>
                <form id="verificationForm" class="bg-white p-4 rounded-4 shadow-sm">
                    <input type="hidden" id="verify_order_id" name="order_id" value="">
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="account_number" class="form-label fw-semibold">Customer Account</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 rounded-start-3 shadow-sm"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control bg-light border-0 rounded-end-3 shadow-sm" id="account_number" name="account_number" readonly>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold d-flex align-items-center">
                                <span>Our KBZPay Account</span>
                                <span class="badge bg-success ms-2 rounded-pill">For Verification</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-success-subtle border-success border-opacity-25 rounded-start-3 shadow-sm"><i class="bi bi-building"></i></span>
                                <input type="text" class="form-control bg-success-subtle border-success border-opacity-25 text-success fw-bold rounded-0 shadow-sm" id="company_account" value="" readonly>
                                <button class="btn btn-success rounded-end-3 shadow-sm" type="button" onclick="copyAccountNumber()">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <div class="form-text small mt-1">Compare with the account number on the slip.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_status" class="form-label fw-semibold">Payment Status</label>
                        <select class="form-select border-0 shadow-sm rounded-3" id="payment_status" name="payment_status">
                            <option value="1">Completed</option>
                            <option value="2">Failed</option>
                            <option value="3">Refunded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label fw-semibold">Transaction ID</label>
                        <div class="input-group">
                            <input type="text" class="form-control border-0 shadow-sm rounded-start-3" id="transaction_id" name="transaction_id" 
                                   placeholder="Enter transaction ID from the slip" required>
                            <button class="btn btn-primary rounded-end-3 shadow-sm" type="button" id="scanTransactionBtn" title="Scan Payment Slip">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                        <div id="transaction_scan_status" class="small text-muted mt-1"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount_verified" class="form-label fw-semibold">Amount Verified (MMK)</label>
                        <div class="input-group">
                            <span class="input-group-text border-0 shadow-sm rounded-start-3">MMK</span>
                            <input type="number" class="form-control border-0 shadow-sm rounded-end-3" id="amount_verified" name="amount_verified" 
                                step="1" min="0" value="<?php echo $_GET['amount'] ?? ''; ?>" required>
                        </div>
                        <div class="form-text small mt-1">Amount is pre-filled from order but can be modified if needed.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_notes" class="form-label fw-semibold">Notes</label>
                        <textarea class="form-control border-0 shadow-sm rounded-3" id="verification_notes" name="verification_notes" 
                                  rows="3" placeholder="Add any verification notes here..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" onclick="submitPaymentVerification()">
                        <i class="bi bi-shield-check me-2"></i>Verify Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Verification History Modal -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 0.75rem; overflow: hidden;">
            <div class="modal-header border-0 p-4" style="background: linear-gradient(135deg, #8BC6EC 0%, #9599E2 100%);">
                <div class="d-flex align-items-center">
                    <div class="bg-white p-2 rounded-circle shadow me-3">
                        <i class="bi bi-clock-history fs-4 text-primary"></i>
                    </div>
                    <h5 class="modal-title fs-4 fw-bold text-white m-0" id="paymentHistoryModalLabel">Payment Verification History</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="paymentHistoryContent" class="p-4">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading payment history...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light p-3">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <small class="text-muted">Complete verification history for this order</small>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/admin.js"></script>
<script src="assets/js/orders.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check for resubmitted payments and show notification
    const resubmittedRow = document.querySelector('tr[data-resubmitted="true"]');
    if (resubmittedRow) {
        const orderId = resubmittedRow.dataset.orderId;
        
        // Create notification
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning alert-dismissible fade show';
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                <div>
                    <strong>Payment Resubmitted!</strong> Order #${orderId} has a new payment slip that needs verification.
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert at the top of the page
        document.getElementById('orderMessage').appendChild(alertDiv);
        
        // Scroll to the row and highlight it
        resubmittedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Add pulsing effect
        setTimeout(() => {
            resubmittedRow.style.transition = 'all 0.5s ease-in-out';
            resubmittedRow.style.boxShadow = '0 0 15px 5px rgba(255, 193, 7, 0.5)';
            
            setTimeout(() => {
                resubmittedRow.style.boxShadow = 'none';
            }, 2000);
        }, 500);
    }
    
    // Add highlighting for awaiting resubmission status
    document.querySelectorAll('tr').forEach(row => {
        const paymentCell = row.querySelector('td:nth-child(5)');
        if (paymentCell && paymentCell.innerHTML.includes('Needs Resubmit')) {
            row.classList.add('bg-warning-subtle');
        }
    });
});

// Function to verify Cash on Delivery payment without requiring a payment slip
function verifyCODPayment(orderId) {
    if (!confirm('Are you sure you want to mark this Cash on Delivery payment as verified?')) {
        return;
    }
    
    const verificationData = {
        order_id: orderId,
        verify: true,
        verification_details: {
            transaction_id: 'COD-' + orderId, // Generate a pseudo transaction ID
            amount_verified: document.querySelector(`tr[data-order-id="${orderId}"]`).dataset.amount,
            verification_notes: 'Cash on Delivery payment marked as verified by admin',
            payment_status: 1 // Completed
        }
    };
    
    // Call the verification API
    fetch('/hm/api/orders/verify_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(verificationData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showToast('success', 'Cash on Delivery payment verified successfully');
            // Reload page to reflect changes
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showToast('error', data.message || 'Failed to verify payment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred while verifying payment');
    });
}
</script>

</body>
</html> 