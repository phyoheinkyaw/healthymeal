<?php
require_once '../includes/auth_check.php';

$role = checkRememberToken();
if (!$role || $role != 1) {
    header("Location: /hm/login.php");
    exit();
}

$message = '';
$error = '';
$edit_mode = false;
$edit_id = 0;

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $del_id = (int)$_POST['delete_id'];
        
        // Check if delivery option is used in any orders before deleting
        $check_stmt = $mysqli->prepare("SELECT COUNT(*) FROM orders WHERE delivery_option_id = ?");
        $check_stmt->bind_param('i', $del_id);
        $check_stmt->execute();
        $check_stmt->bind_result($order_count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($order_count > 0) {
            $error = 'Cannot delete: This delivery option is used in ' . $order_count . ' orders. Deactivate it instead.';
        } else {
            $stmt = $mysqli->prepare("DELETE FROM delivery_options WHERE delivery_option_id = ?");
            $stmt->bind_param('i', $del_id);
            if ($stmt->execute()) {
                $message = 'Delivery option deleted successfully.';
            } else {
                $error = 'Failed to delete delivery option.';
            }
            $stmt->close();
        }
    } else if (isset($_POST['toggle_id'])) {
        // Toggle active status (non-AJAX fallback)
        $toggle_id = (int)$_POST['toggle_id'];
        $stmt = $mysqli->prepare("UPDATE delivery_options SET is_active = 1 - is_active WHERE delivery_option_id = ?");
        $stmt->bind_param('i', $toggle_id);
        if ($stmt->execute()) {
            $message = 'Delivery option status updated.';
        } else {
            $error = 'Failed to update status.';
        }
        $stmt->close();
    } else {
        // Add or update delivery option
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $fee = (int)($_POST['fee'] ?? 0);
        $time_slot = $_POST['time_slot'] ?? '';
        $cutoff_time = $_POST['cutoff_time'] ?? '';
        $max_orders = (int)($_POST['max_orders_per_slot'] ?? 10);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $id = $_POST['id'] ?? null;
        
        if (empty($name) || empty($time_slot) || empty($cutoff_time)) {
            $error = 'Name, time slot, and cutoff time are required.';
        } else {
            if ($id) {
                // Update existing delivery option
                $stmt = $mysqli->prepare("UPDATE delivery_options SET name = ?, description = ?, fee = ?, time_slot = ?, cutoff_time = ?, max_orders_per_slot = ?, is_active = ? WHERE delivery_option_id = ?");
                $stmt->bind_param('ssissiii', $name, $description, $fee, $time_slot, $cutoff_time, $max_orders, $is_active, $id);
                if ($stmt->execute()) {
                    $message = 'Delivery option updated successfully.';
                } else {
                    $error = 'Failed to update delivery option.';
                }
            } else {
                // Add new delivery option
                $stmt = $mysqli->prepare("INSERT INTO delivery_options (name, description, fee, time_slot, cutoff_time, max_orders_per_slot, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssissii', $name, $description, $fee, $time_slot, $cutoff_time, $max_orders, $is_active);
                if ($stmt->execute()) {
                    $message = 'Delivery option added successfully.';
                } else {
                    $error = 'Failed to add delivery option.';
                }
            }
            $stmt->close();
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_mode = true;
    
    $stmt = $mysqli->prepare("SELECT * FROM delivery_options WHERE delivery_option_id = ?");
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $edit_data = $row;
    } else {
        $error = 'Delivery option not found.';
        $edit_mode = false;
    }
    $stmt->close();
}

// Fetch all delivery options
$options = [];
$result = $mysqli->query("SELECT * FROM delivery_options ORDER BY time_slot ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $options[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Options - Healthy Meal Kit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .delivery-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(31, 38, 135, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .delivery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(31, 38, 135, 0.2);
        }
        
        .delivery-card-header {
            background: linear-gradient(135deg, rgba(107, 181, 255, 0.4) 0%, rgba(107, 181, 255, 0.1) 100%);
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .delivery-card-body {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .delivery-card-footer {
            padding: 15px 20px;
            background: rgba(0, 0, 0, 0.02);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .delivery-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .time-badge {
            font-size: 0.9rem;
            font-weight: 500;
            background-color: rgba(107, 181, 255, 0.2);
            color: #0b5ed7;
            border-radius: 6px;
            padding: 4px 10px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .inactive-card {
            opacity: 0.7;
        }
    </style>
</head>

<body>
    <div class="overlay" onclick="toggleSidebar()" style="z-index: 1040"></div>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-toggle">
            <button class="btn btn-dark" type="button" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <main class="main-content">
            <div class="alerts-container position-fixed top-0 start-0 w-100 p-3" style="z-index: 1060"></div>
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <h3 class="page-title"><i class="bi bi-truck me-2"></i>Delivery Options Management</h3>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">
                            <?php echo $edit_mode ? 'Edit Delivery Option' : 'Add New Delivery Option'; ?>
                        </h3>
                    </div>
                    
                    <div class="card-body">
                        <form action="delivery-options.php" method="POST">
                            <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_data['delivery_option_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Name<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                        value="<?php echo $edit_mode ? htmlspecialchars($edit_data['name']) : ''; ?>">
                                    <div class="form-text">A descriptive name for this delivery option.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="fee" class="form-label">Delivery Fee (MMK)<span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="fee" name="fee" min="0" required 
                                        value="<?php echo $edit_mode ? (int)$edit_data['fee'] : ''; ?>">
                                    <div class="form-text">The cost for this delivery option.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="time_slot" class="form-label">Delivery Time Slot (Start Time)<span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="time_slot" name="time_slot" required 
                                        value="<?php echo $edit_mode ? $edit_data['time_slot'] : ''; ?>">
                                    <div class="form-text">The time when delivery start (e.g., 09:00).</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="cutoff_time" class="form-label">Delivery Time Slot (End Time)<span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="cutoff_time" name="cutoff_time" required 
                                        value="<?php echo $edit_mode ? $edit_data['cutoff_time'] : ''; ?>">
                                    <div class="form-text">The time when delivery end (e.g., 12:00).</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="max_orders_per_slot" class="form-label">Maximum Orders<span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="max_orders_per_slot" name="max_orders_per_slot" min="1" required 
                                        value="<?php echo $edit_mode ? (int)$edit_data['max_orders_per_slot'] : '10'; ?>">
                                    <div class="form-text">Maximum number of orders allowed for this time slot.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch mt-4 pt-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" 
                                            <?php echo (!$edit_mode || ($edit_mode && $edit_data['is_active'] == 1)) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                    <div class="form-text">Toggle to enable or disable this delivery option.</div>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $edit_mode ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
                                    <div class="form-text">A detailed description of this delivery option for customers.</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <?php if ($edit_mode): ?>
                                <a href="delivery-options.php" class="btn btn-secondary me-2">
                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary btn-ripple">
                                    <i class="bi bi-save me-1"></i> <?php echo $edit_mode ? 'Update Delivery Option' : 'Add Delivery Option'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-light py-3">
                        <h5 class="mb-0">All Delivery Options</h5>
                        <i class="bi bi-truck text-muted"></i>
                    </div>
                    
                    <div class="card-body p-3">
                        <?php if (count($options) > 0): ?>
                        <div class="delivery-methods-grid">
                            <?php foreach ($options as $option): ?>
                            <div class="delivery-card <?php echo $option['is_active'] ? '' : 'inactive-card'; ?>">
                                <div class="delivery-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($option['name']); ?></h5>
                                        <?php if ($option['is_active']): ?>
                                        <span class="badge bg-success status-badge">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary status-badge">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="delivery-card-body">
                                    <div class="mb-3">
                                        <span class="time-badge">
                                            <i class="bi bi-clock me-1"></i>
                                            Delivery Start Time: <?php echo date('g:i A', strtotime($option['time_slot'])); ?>
                                        </span>
                                        <span class="time-badge">
                                            <i class="bi bi-hourglass-split me-1"></i> 
                                            Estimated Delivery End Time: <?php echo date('g:i A', strtotime($option['cutoff_time'])); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="mb-2">
                                        <strong>Fee:</strong> 
                                        <?php echo number_format($option['fee']) . ' MMK'; ?>
                                    </p>
                                    
                                    <p class="mb-2">
                                        <strong>Max Orders:</strong> 
                                        <?php echo $option['max_orders_per_slot']; ?>
                                    </p>
                                    
                                    <?php if ($option['description']): ?>
                                    <p class="text-muted mt-2"><?php echo htmlspecialchars($option['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="delivery-card-footer">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="delivery-options.php?edit=<?php echo $option['delivery_option_id']; ?>" class="btn btn-sm btn-outline-primary btn-ripple">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            
                                            <button type="button" class="btn btn-sm btn-outline-<?php echo $option['is_active'] ? 'warning' : 'success'; ?> btn-ripple toggle-status-btn"
                                                   data-id="<?php echo $option['delivery_option_id']; ?>" 
                                                   data-name="<?php echo htmlspecialchars($option['name'], ENT_QUOTES); ?>" 
                                                   data-status="<?php echo $option['is_active']; ?>">
                                                <i class="bi bi-toggle-<?php echo $option['is_active'] ? 'on' : 'off'; ?>"></i>
                                                <?php echo $option['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </div>
                                        
                                        <form action="delivery-options.php" method="POST" class="d-inline delete-form">
                                            <input type="hidden" name="delete_id" value="<?php echo $option['delivery_option_id']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-ripple" onclick="confirmDelete(this, '<?php echo htmlspecialchars($option['name'], ENT_QUOTES); ?>')">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> No delivery options found. Add your first delivery option above.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script src="assets/js/delivery-options.js"></script>
    <script>
    // Variables for alert messages from PHP
    const hasSuccessMessage = <?php echo !empty($message) ? 'true' : 'false'; ?>;
    const successMessage = <?php echo !empty($message) ? json_encode($message) : '""'; ?>;
    const hasErrorMessage = <?php echo !empty($error) ? 'true' : 'false'; ?>;
    const errorMessage = <?php echo !empty($error) ? json_encode($error) : '""'; ?>;
    
    // Show alert messages if they exist when page loads
    $(document).ready(function() {
        if (hasSuccessMessage) {
            showGenericAlertModal('Success', successMessage, 'success');
        }
        
        if (hasErrorMessage) {
            showGenericAlertModal('Error', errorMessage, 'danger');
        }
    });
    
    function confirmDelete(btn, name) {
        showGenericConfirmModal(
            'Delete Delivery Option',
            `Are you sure you want to delete the delivery option "${name}"? This cannot be undone.`,
            'danger',
            function() {
                $(btn).closest('form').submit();
            }
        );
    }
    
    // Handle toggle status button clicks
    $(document).on('click', '.toggle-status-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const currentStatus = $(this).data('status') == 1;
        const newStatus = !currentStatus;
        const statusText = newStatus ? 'activate' : 'deactivate';
        const btn = $(this);
        
        showGenericConfirmModal(
            `${newStatus ? 'Activate' : 'Deactivate'} Delivery Option`,
            `Are you sure you want to ${statusText} the delivery option "${name}"?`,
            newStatus ? 'success' : 'warning',
            function() {
                // Send AJAX request to toggle status
                $.ajax({
                    url: 'api/delivery-options/toggle-status.php',
                    type: 'POST',
                    data: {
                        id: id,
                        status: newStatus ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            showGenericAlertModal('Success', `Delivery option "${name}" has been ${newStatus ? 'activated' : 'deactivated'}.`, newStatus ? 'success' : 'warning', function() {
                                window.location.reload(); // Reload page after status change
                            });
                        } else {
                            showGenericAlertModal('Error', response.message || 'Failed to update status.', 'danger');
                        }
                    },
                    error: function() {
                        showGenericAlertModal('Error', 'An error occurred while updating the delivery option status.', 'danger');
                    }
                });
            }
        );
    });
    
    // Generic Alert Modal function from order-details.php
    function showGenericAlertModal(title, bodyText, type = 'info', onHidden) {
        $('#genericAlertModal').remove(); // Remove previous modal
        const modalHtml = `
        <div class="modal fade" id="genericAlertModal" tabindex="-1" aria-labelledby="genericAlertLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-${type}-subtle">
                <h5 class="modal-title" id="genericAlertLabel">
                    <i class="bi bi-${type === 'danger' ? 'x-octagon-fill text-danger' : (type === 'warning' ? 'exclamation-triangle-fill text-warning' : (type === 'success' ? 'check-circle-fill text-success' : 'info-circle-fill text-info'))} me-2"></i>
                    ${title}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                ${bodyText}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
              </div>
            </div>
          </div>
        </div>`;
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('genericAlertModal'));
        $('#genericAlertModal').on('hidden.bs.modal', function () {
            if (onHidden) onHidden();
            $(this).remove();
        });
        modal.show();
    }
    
    // Generic Confirmation Modal function from order-details.php
    function showGenericConfirmModal(title, bodyText, type = 'primary', onConfirm, onCancel) {
        $('#genericConfirmModal').remove(); // Remove previous modal
        const modalHtml = `
        <div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-labelledby="genericConfirmLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header bg-${type}-subtle">
                <h5 class="modal-title" id="genericConfirmLabel">
                    <i class="bi bi-${type === 'danger' ? 'exclamation-triangle-fill text-danger' : (type === 'warning' ? 'exclamation-triangle-fill text-warning' : 'question-circle-fill text-primary')} me-2"></i>
                    ${title}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                ${bodyText}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="genericConfirmCancelBtn">Cancel</button>
                <button type="button" class="btn btn-${type}" id="genericConfirmOkBtn">Yes, Proceed</button>
              </div>
            </div>
          </div>
        </div>`;
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('genericConfirmModal'));
        
        $('#genericConfirmOkBtn').on('click', function() {
            if (onConfirm) onConfirm();
            modal.hide();
        });
        $('#genericConfirmCancelBtn').on('click', function() {
            if (onCancel) onCancel();
        });
         $('#genericConfirmModal').on('hidden.bs.modal', function () {
            $(this).remove();
        });
        modal.show();
    }
    </script>
</body>

</html> 