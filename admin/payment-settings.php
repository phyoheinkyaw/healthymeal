<?php
require_once '../includes/auth_check.php';

$role = checkRememberToken();
if (!$role || $role !== 'admin') {
    header("Location: /hm/login.php");
    exit();
}

$message = '';
$error = '';
$edit_mode = false;
$edit_qr = '';

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $del_id = (int)$_POST['delete_id'];
        // Get QR code path to delete image file
        $qr_stmt = $mysqli->prepare("SELECT qr_code FROM payment_settings WHERE id=?");
        $qr_stmt->bind_param('i', $del_id);
        $qr_stmt->execute();
        $qr_stmt->bind_result($qr_path);
        $qr_stmt->fetch();
        $qr_stmt->close();
        if ($qr_path && file_exists("../$qr_path")) {
            @unlink("../$qr_path");
        }
        $stmt = $mysqli->prepare("DELETE FROM payment_settings WHERE id=?");
        $stmt->bind_param('i', $del_id);
        if ($stmt->execute()) {
            $message = 'Payment method deleted.';
        } else {
            $error = 'Failed to delete.';
        }
        $stmt->close();
    } else {
        $method = $_POST['payment_method'] ?? '';
        $phone = $_POST['account_phone'] ?? '';
        $id = $_POST['id'] ?? null;
        $qr_code = null;
        $old_qr = null;
        if ($id) {
            // Get old QR path for update
            $qr_stmt = $mysqli->prepare("SELECT qr_code FROM payment_settings WHERE id=?");
            $qr_stmt->bind_param('i', $id);
            $qr_stmt->execute();
            $qr_stmt->bind_result($old_qr);
            $qr_stmt->fetch();
            $qr_stmt->close();
        }
        if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('qr_', true) . '.' . $ext;
            $upload_dir = '../uploads/payment_qr/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $target)) {
                $qr_code = 'uploads/payment_qr/' . $filename;
                // Unlink old QR if exists and updating
                if ($id && $old_qr && file_exists("../$old_qr")) {
                    @unlink("../$old_qr");
                }
            } else {
                $error = 'Failed to upload QR code image.';
            }
        }
        if (!$error) {
            if ($id) {
                $sql = "UPDATE payment_settings SET payment_method=?, account_phone=?";
                $params = [$method, $phone];
                if ($qr_code) {
                    $sql .= ", qr_code=?";
                    $params[] = $qr_code;
                }
                $sql .= " WHERE id=?";
                $params[] = $id;
                $stmt = $mysqli->prepare($sql);
                $types = str_repeat('s', count($params)-1) . 'i';
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $message = 'Payment method updated.';
                } else {
                    $error = 'Failed to update.';
                }
                $stmt->close();
            } else {
                $stmt = $mysqli->prepare("INSERT INTO payment_settings (payment_method, qr_code, account_phone) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $method, $qr_code, $phone);
                if ($stmt->execute()) {
                    $message = 'Payment method added.';
                } else {
                    $error = 'Failed to add.';
                }
                $stmt->close();
            }
        }
    }
}
// Fetch all payment methods
$methods = [];
$result = $mysqli->query("SELECT * FROM payment_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $methods[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Method Settings - Healthy Meal Kit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .avatar-qr {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
        }
        /* Match categories.php DataTable padding and style */
        #paymentTable th, #paymentTable td {
            padding: 1rem 1.5rem !important;
            vertical-align: middle;
        }
        #paymentTable thead tr th {
            background: rgba(255, 107, 107, 0.5);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: none;
        }
        #paymentTable tbody tr td {
            background: transparent;
            border-bottom-color: rgba(255, 255, 255, 0.2);
            color: #333;
        }
        .img-preview {
            display: block;
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 8px;
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
                    <h3 class="page-title"><i class="bi bi-credit-card-2-front me-2"></i>Payment Method Settings</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal"><i class="bi bi-plus-lg"></i> Add Payment Method</button>
                </div>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light">Payment Methods</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover" id="paymentTable">
                                    <thead>
                                        <tr>
                                            <th style="width:60px">#</th>
                                            <th>Method</th>
                                            <th>Phone</th>
                                            <th>QR Code</th>
                                            <th style="width:160px">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($methods as $m): ?>
                                        <tr>
                                            <td><?= $m['id'] ?></td>
                                            <td><?= htmlspecialchars($m['payment_method']) ?></td>
                                            <td><?= htmlspecialchars($m['account_phone']) ?></td>
                                            <td>
                                                <?php if ($m['qr_code']): ?>
                                                    <img src="../<?= $m['qr_code'] ?>" alt="QR" class="avatar-qr">
                                                <?php else: ?>
                                                    <span class="text-muted">No QR</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-2" onclick="editMethod(<?= $m['id'] ?>, '<?= htmlspecialchars($m['payment_method'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['account_phone'], ENT_QUOTES) ?>', '<?= $m['qr_code'] ? '../' . $m['qr_code'] : '' ?>')"><i class="bi bi-pencil"></i> Edit</button>
                                                <form method="post" class="d-inline delete-form">
                                                    <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteForm(this, '<?= htmlspecialchars($m['payment_method'], ENT_QUOTES) ?>')"><i class="bi bi-trash"></i> Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<!-- Add/Edit Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Payment Method Name</label>
                            <input type="text" name="payment_method" class="form-control" id="edit_method" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Account Phone</label>
                            <input type="number" name="account_phone" class="form-control" id="edit_phone" required pattern="[0-9]+" inputmode="numeric" min="0">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">QR Code Image (optional, will replace if set)</label>
                            <input type="file" name="qr_code" class="form-control" id="qrInput">
                            <img src="" id="qrPreview" class="img-preview d-none mt-2" alt="QR Preview">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/js/admin.js"></script>
<script>
$(document).ready(function() {
    $('#paymentTable').DataTable({
        order: [[1, 'asc']],
        responsive: true,
        language: {
            search: "Search payment methods:",
            lengthMenu: "Show _MENU_ methods per page",
            info: "Showing _START_ to _END_ of _TOTAL_ methods",
            emptyTable: "No payment methods available"
        }
    });
    // Image preview for QR upload
    $('#qrInput').on('change', function(e) {
        const [file] = this.files;
        if (file) {
            $('#qrPreview').removeClass('d-none').attr('src', URL.createObjectURL(file));
        } else {
            $('#qrPreview').addClass('d-none').attr('src', '');
        }
    });
    // Reset modal on open for add
    $('#addPaymentModal').on('show.bs.modal', function(e) {
        if (!$('#edit_id').val()) {
            $('#modalTitle').text('Add Payment Method');
            $('#qrPreview').addClass('d-none').attr('src', '');
            $('#paymentForm')[0].reset();
        }
    });
});
function editMethod(id, method, phone, qr) {
    $('#edit_id').val(id);
    $('#edit_method').val(method);
    $('#edit_phone').val(phone);
    if (qr) {
        $('#qrPreview').removeClass('d-none').attr('src', qr);
    } else {
        $('#qrPreview').addClass('d-none').attr('src', '');
    }
    $('#modalTitle').text('Edit Payment Method');
    $('#addPaymentModal').modal('show');
}
function confirmDeleteForm(btn, name) {
    showDeleteConfirmModal(function() {
        // Submit the parent form
        $(btn).closest('form').submit();
    }, {
        title: 'Delete Payment Method',
        message: `Are you sure you want to delete <b>"${name}"</b>? This action cannot be undone.`,
        icon: '<i class="bi bi-trash-fill text-danger me-2"></i>'
    });
}
function toggleSidebar() {
    document.querySelector('.admin-container').classList.toggle('sidebar-open');
    document.querySelector('.overlay').classList.toggle('show');
}
</script>
</body>
</html>
