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
    } else if (isset($_POST['toggle_id'])) {
        // Toggle active status
        $toggle_id = (int)$_POST['toggle_id'];
        $stmt = $mysqli->prepare("UPDATE payment_settings SET is_active = 1 - is_active WHERE id=?");
        $stmt->bind_param('i', $toggle_id);
        if ($stmt->execute()) {
            $message = 'Payment method status updated.';
        } else {
            $error = 'Failed to update status.';
        }
        $stmt->close();
    } else {
        $method = $_POST['payment_method'] ?? '';
        $phone = $_POST['account_phone'] ?? '';
        $icon_class = $_POST['icon_class'] ?? 'bi bi-credit-card';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
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
                $sql = "UPDATE payment_settings SET payment_method=?, account_phone=?, icon_class=?, is_active=?, description=?, bank_info=?";
                $description = $_POST['description'] ?? null;
                $bank_info = $_POST['bank_info'] ?? null;
                $params = [$method, $phone, $icon_class, $is_active, $description, $bank_info];
                if ($qr_code) {
                    $sql .= ", qr_code=?";
                    $params[] = $qr_code;
                }
                $sql .= " WHERE id=?";
                $params[] = $id;
                $stmt = $mysqli->prepare($sql);
                $types = '';
                foreach ($params as $i => $param) {
                    if ($i === 3 || $i === count($params) - 1) { // is_active and id parameters
                        $types .= 'i';
                    } else {
                        $types .= 's';
                    }
                }
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $message = 'Payment method updated.';
                } else {
                    $error = 'Failed to update.';
                }
                $stmt->close();
            } else {
                $description = $_POST['description'] ?? null;
                $bank_info = $_POST['bank_info'] ?? null;
                $stmt = $mysqli->prepare("INSERT INTO payment_settings (payment_method, qr_code, account_phone, icon_class, is_active, description, bank_info) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssiss', $method, $qr_code, $phone, $icon_class, $is_active, $description, $bank_info);
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
        width: 80px;
        height: 80px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid #dee2e6;
        background: #f8f9fa;
        transition: transform 0.2s;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    }

    .avatar-qr:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    /* Payment cards layout */
    .payment-methods-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .payment-card {
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

    .payment-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(31, 38, 135, 0.2);
    }

    .payment-card-header {
        background: linear-gradient(135deg, rgba(255, 107, 107, 0.4) 0%, rgba(255, 107, 107, 0.1) 100%);
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    }

    .payment-card-body {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .payment-card-footer {
        padding: 15px 20px;
        background: rgba(0, 0, 0, 0.02);
        border-top: 1px solid rgba(255, 255, 255, 0.18);
    }

    .payment-method-name {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }

    .payment-method-phone {
        font-size: 0.95rem;
        color: #555;
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .payment-qr-container {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 15px 0;
        margin-top: auto;
    }

    .no-qr-badge {
        padding: 15px 20px;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 10px;
        color: #666;
        font-style: italic;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-header {
        background: linear-gradient(135deg, rgba(255, 107, 107, 0.4) 0%, rgba(255, 107, 107, 0.1) 100%);
        border-radius: 15px 15px 0 0;
    }

    /* Custom file input styling */
    .qr-upload-container {
        border: 2px dashed rgba(0, 123, 255, 0.3);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: all 0.2s;
        margin-top: 10px;
        background: rgba(0, 123, 255, 0.03);
    }

    .qr-upload-container:hover {
        border-color: rgba(0, 123, 255, 0.5);
        background: rgba(0, 123, 255, 0.05);
    }

    .img-preview {
        display: block;
        width: 120px;
        height: 120px;
        object-fit: cover;
        border: 1px solid #dee2e6;
        border-radius: 12px;
        margin: 15px auto 5px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Empty state styling */
    .empty-state {
        padding: 40px;
        text-align: center;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.8);
        border: 1px dashed rgba(0, 0, 0, 0.1);
    }

    .empty-state i {
        font-size: 3rem;
        color: rgba(0, 123, 255, 0.3);
        margin-bottom: 15px;
    }

    .icon-picker-grid {
        max-height: 200px;
        overflow-y: auto;
    }

    .icon-btn {
        height: 50px;
        padding: 10px;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .icon-btn.active {
        background-color: #0d6efd;
        color: white;
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
                        <h3 class="page-title"><i class="bi bi-credit-card-2-front me-2"></i>Payment Method Settings
                        </h3>
                        <button type="button" class="btn btn-primary btn-ripple" data-bs-toggle="modal"
                            data-bs-target="#addPaymentModal">
                            <i class="bi bi-plus-lg"></i> Add Payment Method
                        </button>
                    </div>
                </div>
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (empty($methods)): ?>
                <!-- Empty state when no payment methods -->
                <div class="empty-state">
                    <i class="bi bi-credit-card"></i>
                    <h4>No Payment Methods Yet</h4>
                    <p class="text-muted mb-4">Add your first payment method to start accepting payments from customers.
                    </p>
                    <button type="button" class="btn btn-primary btn-ripple" data-bs-toggle="modal"
                        data-bs-target="#addPaymentModal">
                        <i class="bi bi-plus-lg"></i> Add Payment Method
                    </button>
                </div>
                <?php else: ?>
                <div class="payment-methods-grid">
                    <?php foreach ($methods as $m): ?>
                    <div class="payment-card">
                        <div class="payment-card-header">
                            <div class="payment-method-name">
                                <i class="<?= htmlspecialchars($m['icon_class']) ?>"></i>
                                <?= htmlspecialchars($m['payment_method']) ?>
                                <?php if ($m['is_active']): ?>
                                <span class="badge bg-success ms-2">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary ms-2">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <div class="payment-method-phone">
                                <i class="bi bi-telephone me-2"></i>
                                <?= htmlspecialchars($m['account_phone']) ?>
                            </div>
                        </div>
                        <div class="payment-card-body">
                            <div class="payment-qr-container">
                                <?php if ($m['qr_code']): ?>
                                <img src="../<?= $m['qr_code'] ?>" alt="QR Code" class="avatar-qr">
                                <?php elseif (!empty($m['bank_info'])): ?>
                                <div class="bank-info-badge">
                                    <i class="bi bi-bank me-2"></i> Bank Information Available
                                </div>
                                <?php else: ?>
                                <div class="no-qr-badge">
                                    <i class="bi bi-qr-code me-2"></i> No Payment Information Available
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="payment-card-footer">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <button class="btn btn-sm btn-outline-warning btn-ripple"
                                        onclick="editMethod(<?= $m['id'] ?>, '<?= htmlspecialchars($m['payment_method'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['account_phone'], ENT_QUOTES) ?>', '<?= $m['qr_code'] ? '../' . $m['qr_code'] : '' ?>', '<?= htmlspecialchars($m['icon_class'], ENT_QUOTES) ?>', <?= $m['is_active'] ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="toggle_id" value="<?= $m['id'] ?>">
                                        <button type="submit"
                                            class="btn btn-sm <?= $m['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?> btn-ripple">
                                            <i class="bi <?= $m['is_active'] ? 'bi-toggle-off' : 'bi-toggle-on' ?>"></i>
                                            <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                                <form method="post" class="d-inline delete-form">
                                    <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-ripple"
                                        onclick="confirmDeleteForm(this, '<?= htmlspecialchars($m['payment_method'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Data Table for Additional Reference -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-light py-3">
                        <h5 class="mb-0">Payment Methods List</h5>
                        <i class="bi bi-table text-muted"></i>
                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover" id="paymentTable">
                                <thead>
                                    <tr>
                                        <th style="width:60px">#</th>
                                        <th>Method</th>
                                        <th>Phone</th>
                                        <th>QR Code</th>
                                        <th>Status</th>
                                        <th style="width:210px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($methods as $m): ?>
                                    <tr>
                                        <td><?= $m['id'] ?></td>
                                        <td>
                                            <i class="<?= htmlspecialchars($m['icon_class']) ?> me-2"></i>
                                            <?= htmlspecialchars($m['payment_method']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($m['account_phone']) ?></td>
                                        <td>
                                            <?php if ($m['qr_code']): ?>
                                            <img src="../<?= $m['qr_code'] ?>" alt="QR" class="avatar-qr"
                                                style="width:50px;height:50px;">
                                            <?php else: ?>
                                            <span class="text-muted">No QR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($m['is_active']): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>
                                                Active</span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>
                                                Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-warning btn-ripple"
                                                onclick="editMethod(<?= $m['id'] ?>, '<?= htmlspecialchars($m['payment_method'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['account_phone'], ENT_QUOTES) ?>', '<?= $m['qr_code'] ? '../' . $m['qr_code'] : '' ?>', '<?= htmlspecialchars($m['icon_class'], ENT_QUOTES) ?>', <?= $m['is_active'] ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="toggle_id" value="<?= $m['id'] ?>">
                                                <button type="submit"
                                                    class="btn btn-sm <?= $m['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?> btn-ripple">
                                                    <i
                                                        class="bi <?= $m['is_active'] ? 'bi-toggle-off' : 'bi-toggle-on' ?>"></i>
                                                    <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline delete-form">
                                                <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-ripple"
                                                    onclick="confirmDeleteForm(this, '<?= htmlspecialchars($m['payment_method'], ENT_QUOTES) ?>')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
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
        </main>
    </div>
    <!-- Add/Edit Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-credit-card-2-front me-2"></i>
                        <span id="modalTitleText">Add Payment Method</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" enctype="multipart/form-data" id="paymentForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payment Method Name</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                                <input type="text" name="payment_method" class="form-control" id="edit_method" required
                                    placeholder="e.g. Visa, MasterCard, PayPal">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Icon</label>
                            <div class="icon-selection-container">
                                <input type="hidden" name="icon_class" id="selected_icon" value="bi bi-credit-card">
                                <div class="input-group">
                                    <span class="input-group-text"><i id="icon_preview"
                                            class="bi bi-credit-card"></i></span>
                                    <input type="text" class="form-control" id="icon_class_input"
                                        placeholder="Icon class (e.g. bi bi-credit-card)" value="bi bi-credit-card">
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#iconPicker">
                                        <i class="bi bi-grid"></i> Choose
                                    </button>
                                </div>
                                <div class="collapse mt-2" id="iconPicker">
                                    <div class="card card-body">
                                        <div class="input-group mb-2">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" id="iconSearchInput"
                                                placeholder="Search icons...">
                                        </div>
                                        <div class="row row-cols-4 g-2 icon-picker-grid">
                                            <!-- Common payment icons -->
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-credit-card"><i
                                                        class="bi bi-credit-card"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-credit-card-2-front"><i
                                                        class="bi bi-credit-card-2-front"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-credit-card-fill"><i
                                                        class="bi bi-credit-card-fill"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-credit-card-2-front-fill"><i
                                                        class="bi bi-credit-card-2-front-fill"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-cash"><i class="bi bi-cash"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-cash-coin"><i class="bi bi-cash-coin"></i></button>
                                            </div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-cash-stack"><i
                                                        class="bi bi-cash-stack"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-phone"><i class="bi bi-phone"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-phone-fill"><i
                                                        class="bi bi-phone-fill"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-bank"><i class="bi bi-bank"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-bank2"><i class="bi bi-bank2"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-wallet"><i class="bi bi-wallet"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-wallet-fill"><i
                                                        class="bi bi-wallet-fill"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-wallet2"><i class="bi bi-wallet2"></i></button>
                                            </div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-currency-dollar"><i
                                                        class="bi bi-currency-dollar"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-currency-bitcoin"><i
                                                        class="bi bi-currency-bitcoin"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-currency-euro"><i
                                                        class="bi bi-currency-euro"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-currency-pound"><i
                                                        class="bi bi-currency-pound"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-currency-yen"><i
                                                        class="bi bi-currency-yen"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-currency-exchange"><i
                                                        class="bi bi-currency-exchange"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-coin"><i class="bi bi-coin"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-receipt"><i class="bi bi-receipt"></i></button>
                                            </div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-receipt-cutoff"><i
                                                        class="bi bi-receipt-cutoff"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-qr-code"><i class="bi bi-qr-code"></i></button>
                                            </div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-qr-code-scan"><i
                                                        class="bi bi-qr-code-scan"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-shop"><i class="bi bi-shop"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-shop-window"><i
                                                        class="bi bi-shop-window"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-bag"><i class="bi bi-bag"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-bag-fill"><i class="bi bi-bag-fill"></i></button>
                                            </div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-cart"><i class="bi bi-cart"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-cart-fill"><i class="bi bi-cart-fill"></i></button>
                                            </div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-laptop"><i class="bi bi-laptop"></i></button></div>
                                            <div class="col icon-item"><button type="button"
                                                    class="btn btn-outline-primary w-100 icon-btn"
                                                    data-icon="bi bi-globe"><i class="bi bi-globe"></i></button></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Phone</label>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="number" name="account_phone" class="form-control" id="edit_phone" required
                                    pattern="[0-9]+" inputmode="numeric" min="0"
                                    placeholder="Phone number for this payment account">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payment Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"
                                placeholder="Describe how to use this payment method..."></textarea>
                            <div class="form-text">
                                This description will be shown to customers on the checkout page.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payment Information</label>
                            <ul class="nav nav-tabs" id="paymentInfoTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="qr-tab" data-bs-toggle="tab"
                                        data-bs-target="#qr-content" type="button" role="tab" aria-controls="qr-content"
                                        aria-selected="true">QR Code</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="bank-tab" data-bs-toggle="tab"
                                        data-bs-target="#bank-content" type="button" role="tab"
                                        aria-controls="bank-content" aria-selected="false">Bank Information</button>
                                </li>
                            </ul>
                            <div class="tab-content border border-top-0 p-3 rounded-bottom" id="paymentInfoTabsContent">
                                <div class="tab-pane fade show active" id="qr-content" role="tabpanel"
                                    aria-labelledby="qr-tab">
                                    <div class="qr-upload-container">
                                        <input type="file" name="qr_code" class="form-control d-none" id="qrInput"
                                            accept="image/*">
                                        <label for="qrInput" class="mb-2" style="cursor: pointer;">
                                            <i class="bi bi-cloud-arrow-up fs-3 d-block mb-2"></i>
                                            <span id="fileSelectionText">Click to select QR code image</span>
                                        </label>
                                        <div id="qrPreviewContainer" class="text-center d-none">
                                            <img src="" id="qrPreview" class="img-preview" alt="QR Preview">
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-2"
                                                id="removeQrBtn">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="bank-content" role="tabpanel" aria-labelledby="bank-tab">
                                    <textarea class="form-control" name="bank_info" id="edit_bank_info" rows="5"
                                        placeholder="Enter bank account details here..."></textarea>
                                    <div class="form-text">
                                        Include bank name, account number, branch, and any other relevant information.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="isActive" name="is_active" checked>
                            <label class="form-check-label" for="isActive">
                                <i class="bi bi-toggle-on text-success me-1"></i> Active
                            </label>
                            <div class="form-text">
                                Active payment methods are displayed to customers during checkout.
                                Inactive methods are hidden from the checkout page.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-ripple">
                            <i class="bi bi-save"></i> Save Payment Method
                        </button>
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
        // Image preview for QR upload
        $('#qrInput').on('change', function(e) {
            const [file] = this.files;
            if (file) {
                $('#qrPreviewContainer').removeClass('d-none');
                $('#qrPreview').attr('src', URL.createObjectURL(file));
                $('#fileSelectionText').text(file.name);
            } else {
                $('#qrPreviewContainer').addClass('d-none');
                $('#qrPreview').attr('src', '');
                $('#fileSelectionText').text('Click to select QR code image');
            }
        });

        // Icon picker with search
        $('.icon-btn').on('click', function() {
            const iconClass = $(this).data('icon');
            $('#selected_icon').val(iconClass);
            $('#icon_class_input').val(iconClass);
            $('#icon_preview').attr('class', iconClass);
            $('.icon-btn').removeClass('active');
            $(this).addClass('active');
            $('#iconPicker').collapse('hide');
        });

        // Icon search functionality
        $('#iconSearchInput').on('input', function() {
            const searchText = $(this).val().toLowerCase();

            if (searchText.length === 0) {
                $('.icon-item').show();
                return;
            }

            $('.icon-item').each(function() {
                const iconClass = $(this).find('.icon-btn').data('icon').toLowerCase();
                if (iconClass.includes(searchText)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Remove QR button
        $('#removeQrBtn').on('click', function() {
            $('#qrInput').val('');
            $('#qrPreviewContainer').addClass('d-none');
            $('#qrPreview').attr('src', '');
            $('#fileSelectionText').text('Click to select QR code image');
        });

        // Reset modal on open for add
        $('#addPaymentModal').on('show.bs.modal', function(e) {
            if (!$('#edit_id').val()) {
                $('#modalTitleText').text('Add Payment Method');
                $('#qrPreviewContainer').addClass('d-none');
                $('#qrPreview').attr('src', '');
                $('#fileSelectionText').text('Click to select QR code image');
                $('#paymentForm')[0].reset();
                $('#icon_preview').attr('class', 'bi bi-credit-card');
                $('#selected_icon').val('bi bi-credit-card');
                $('#icon_class_input').val('bi bi-credit-card');
                $('#edit_description').val('');
                $('#edit_bank_info').val('');
                $('.icon-btn').removeClass('active');
                $('.icon-btn[data-icon="bi bi-credit-card"]').addClass('active');

                // Show QR tab by default
                $('#qr-tab').tab('show');
            }
        });
    });

    function editMethod(id, method, phone, qr, iconClass, isActive) {
        $('#edit_id').val(id);
        $('#edit_method').val(method);
        $('#edit_phone').val(phone);

        // Set icon
        $('#selected_icon').val(iconClass);
        $('#icon_class_input').val(iconClass);
        $('#icon_preview').attr('class', iconClass);
        $('.icon-btn').removeClass('active');
        $(`.icon-btn[data-icon="${iconClass}"]`).addClass('active');

        // Set active status
        $('#isActive').prop('checked', isActive === 1);

        // Fetch additional details using AJAX
        $.ajax({
            url: 'api/get_payment_method_details.php',
            type: 'GET',
            data: {
                id: id
            },
            dataType: 'json',
            success: function(data) {
                // Set description
                $('#edit_description').val(data.description || '');

                // Set bank info
                $('#edit_bank_info').val(data.bank_info || '');

                // Select the appropriate tab based on what data is available
                if (data.bank_info && !qr) {
                    // If bank info is available but no QR code, show the bank tab
                    $('#bank-tab').tab('show');
                } else {
                    // Otherwise show the QR tab by default
                    $('#qr-tab').tab('show');
                }
            },
            error: function() {
                console.error('Failed to load payment method details');
            }
        });

        if (qr) {
            $('#qrPreviewContainer').removeClass('d-none');
            $('#qrPreview').attr('src', qr);
            $('#fileSelectionText').text('Change QR code image');
        } else {
            $('#qrPreviewContainer').addClass('d-none');
            $('#qrPreview').attr('src', '');
            $('#fileSelectionText').text('Click to select QR code image');
        }

        $('#modalTitleText').text('Edit Payment Method');
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
    </script>
</body>

</html>