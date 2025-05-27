<?php
session_start();
require_once 'config/connection.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get and validate order ID
$error = '';
$success = '';
$order_id = isset($_GET['order_id']) ? filter_var($_GET['order_id'], FILTER_VALIDATE_INT) : 0;

if (!$order_id) {
    $error = "Invalid order ID.";
}

// Check if order belongs to current user and get payment details
$order = [];
$payment_method = '';
$latest_payment_status = null;
$payment_notes = '';

if (!$error) {
    $stmt = $mysqli->prepare("
        SELECT o.*, os.status_name, ps.payment_method
        FROM orders o
        LEFT JOIN order_status os ON o.status_id = os.status_id
        LEFT JOIN payment_settings ps ON o.payment_method_id = ps.id
        WHERE o.order_id = ? AND o.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error = "Order not found or you don't have permission to access it.";
    } else {
        $order = $result->fetch_assoc();
        $payment_method = $order['payment_method'];
        
        // Get the latest payment verification status
        $verificationStmt = $mysqli->prepare("
            SELECT pv.payment_status, pv.verification_notes 
            FROM payment_verifications pv
            JOIN payment_history ph ON pv.payment_id = ph.payment_id
            WHERE ph.order_id = ?
            ORDER BY pv.created_at DESC 
            LIMIT 1
        ");
        
        if ($verificationStmt) {
            $verificationStmt->bind_param("i", $order_id);
            $verificationStmt->execute();
            $verificationResult = $verificationStmt->get_result();
            if ($verificationRow = $verificationResult->fetch_assoc()) {
                $latest_payment_status = $verificationRow['payment_status'];
                $payment_notes = $verificationRow['verification_notes'];
            }
            $verificationStmt->close();
        }
    }
}

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    // Check if file is uploaded
    if (!isset($_FILES['transfer_slip']) || $_FILES['transfer_slip']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a valid file.";
    } else {
        $file = $_FILES['transfer_slip'];
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_type, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $error = "Only JPG, PNG and PDF files are allowed.";
        }
        // Validate file size (max 5MB)
        else if ($file['size'] > 5 * 1024 * 1024) {
            $error = "File size should not exceed 5MB.";
        }
        else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'uploads/payment_slips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = uniqid('payment_slip_') . '.' . $file_type;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update order with new payment slip
                $stmt = $mysqli->prepare("
                    UPDATE orders 
                    SET transfer_slip = ?
                    WHERE order_id = ? AND user_id = ?
                ");
                $stmt->bind_param("sii", $upload_path, $order_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    // Store payment history
                    $amount = $order['total_amount'];
                    $paymentStmt = $mysqli->prepare("
                        INSERT INTO payment_history (order_id, payment_method_id, amount, transaction_id) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $transaction_id = uniqid('TRANS');
                    $paymentStmt->bind_param("idss", $order_id, $order['payment_method_id'], $amount, $transaction_id);
                    $paymentStmt->execute();
                    $payment_id = $mysqli->insert_id;
                    
                    // Add initial verification record if payment_id was created
                    if ($payment_id) {
                        // Check if this is a resubmission by looking for existing transfer_slip
                        $is_resubmission = !empty($order['transfer_slip']);
                        
                        // Create appropriate verification note based on whether this is a resubmission
                        $verification_note = $is_resubmission 
                            ? 'RESUBMITTED PAYMENT: Previous payment slip was replaced. Requires re-verification.' 
                            : 'Payment slip uploaded. Awaiting verification.';
                        
                        $verifyStmt = $mysqli->prepare("
                            INSERT INTO payment_verifications (payment_id, order_id, payment_status, verification_notes, transaction_id, amount_verified, verified_by_id) 
                            VALUES (?, ?, 0, ?, ?, ?, 1)
                        ");
                        // Use the same transaction_id from payment history
                        $verifyStmt->bind_param("iisss", $payment_id, $order_id, $verification_note, $transaction_id, $amount);
                        $verifyStmt->execute();
                    }
                    
                    // Add notification with appropriate message based on whether this is a resubmission
                    $notification_message = $is_resubmission
                        ? "Payment slip resubmitted for order #$order_id. Awaiting verification."
                        : "Payment slip uploaded for order #$order_id. Awaiting verification.";
                    
                    $notifyStmt = $mysqli->prepare("
                        INSERT INTO order_notifications (order_id, user_id, message, is_read) 
                        VALUES (?, ?, ?, 0)
                    ");
                    $notifyStmt->bind_param("iis", $order_id, $_SESSION['user_id'], $notification_message);
                    $notifyStmt->execute();
                    
                    $success = "Your payment slip has been uploaded successfully. It will be verified shortly.";
                    
                    // Redirect to orders.php after 3 seconds for a better user experience
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'orders.php?success=1&message=" . urlencode("Your payment has been resubmitted successfully.") . "';
                        }, 3000);
                    </script>";
                    
                    // Notify admin panel by setting a parameter to be used when accessing admin/orders.php
                    $_SESSION['payment_resubmitted'] = $order_id;
                } else {
                    $error = "Failed to update order information.";
                }
            } else {
                $error = "Failed to upload file. Please try again.";
            }
        }
    }
}

// Fetch user data for sidebar
$userStmt = $mysqli->prepare("SELECT username, full_name FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Resubmission - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Dashboard specific styles */
        body {
            background-color: #f8f9fa;
            padding-top: 56px; /* Added space for navbar */
        }
        
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 56px); /* Adjusted for navbar */
        }
        
        .sidebar {
            width: 250px;
            background: #343a40;
            color: #fff;
            position: fixed;
            height: calc(100% - 56px); /* Adjusted for navbar */
            top: 56px; /* Start below navbar */
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 999;
        }
        
        .sidebar .sidebar-header {
            padding: 20px;
            background: #2c3136;
        }
        
        .sidebar ul li a {
            padding: 15px 20px;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background: #2c3136;
            border-left-color: #198754;
        }
        
        .sidebar ul li a i {
            margin-right: 10px;
        }
        
        .main-content {
            width: calc(100% - 250px);
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .content-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            padding: 1rem 1.25rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                width: 100%;
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 250px;
            }
            
            .overlay {
                display: none;
                position: fixed;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.7);
                z-index: 998;
                opacity: 0;
                transition: all 0.5s ease-in-out;
                top: 56px; /* Start below navbar */
            }
            
            .overlay.active {
                display: block;
                opacity: 1;
            }
        }
        
        .toggle-btn {
            background: #198754;
            color: white;
            position: fixed;
            top: 70px; /* Adjusted for navbar */
            left: 15px;
            z-index: 999;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            display: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        @media (max-width: 768px) {
            .toggle-btn {
                display: block;
            }
        }
        
        /* Upload container styles */
        .upload-container {
            transition: all 0.2s;
        }
        
        .upload-container:hover {
            border-color: rgba(0, 123, 255, 0.5) !important;
            background: rgba(0, 123, 255, 0.05) !important;
        }
        
        /* Payment-specific styles */
        .bg-gradient-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
    </style>
</head>

<body>

<?php include 'includes/navbar.php'; ?>

<div class="overlay" onclick="toggleSidebar()"></div>

<button class="toggle-btn" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<div class="dashboard-container">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3>My Account</h3>
            <p class="mb-0"><?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
        </div>
        
        <ul class="list-unstyled">
            <li>
                <a href="index.php" class="d-flex align-items-center">
                    <i class="bi bi-house-door"></i>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="d-flex align-items-center">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="orders.php" class="d-flex align-items-center active">
                    <i class="bi bi-bag"></i>
                    <span>My Orders</span>
                </a>
            </li>
            <li>
                <a href="favorites.php" class="d-flex align-items-center">
                    <i class="bi bi-heart"></i>
                    <span>Favorites</span>
                </a>
            </li>
            <li>
                <a href="meal_plans.php" class="d-flex align-items-center">
                    <i class="bi bi-calendar-check"></i>
                    <span>Meal Plans</span>
                </a>
            </li>
            <li>
                <a href="api/auth/logout.php" class="d-flex align-items-center">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h1 class="m-0">Payment Submission</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-sm-end">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-gradient-primary text-white p-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-credit-card-2-front-fill fs-3 me-3"></i>
                                <div>
                                    <h4 class="mb-0">Payment Slip Submission</h4>
                                    <p class="mb-0 opacity-75">Order #<?= $order_id ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?= $error ?>
                                </div>
                                <div class="text-center mb-4">
                                    <a href="orders.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>Return to Orders
                                    </a>
                                </div>
                            <?php elseif ($success): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <?= $success ?>
                                </div>
                                <div class="text-center mb-4">
                                    <a href="orders.php" class="btn btn-primary">
                                        <i class="bi bi-eye me-2"></i>View All Orders
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php if ($latest_payment_status == 2): ?>
                                    <div class="alert alert-danger mb-4">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <strong>Your previous payment verification failed.</strong><br>
                                        <?php if (!empty($payment_notes)): ?>
                                            <span class="mt-1 d-block">Reason: <?= htmlspecialchars($payment_notes) ?></span>
                                        <?php endif; ?>
                                        Please submit a new payment slip.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-4">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        <strong>Payment Required</strong><br>
                                        Please upload your payment slip to complete your order.
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Order Summary -->
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2 mb-3">Order Summary</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Payment Method:</strong> <?= htmlspecialchars($payment_method) ?></p>
                                            <p class="mb-1"><strong>Order Status:</strong> <?= htmlspecialchars($order['status_name']) ?></p>
                                            <p class="mb-1"><strong>Order Date:</strong> <?= date('F d, Y', strtotime($order['created_at'])) ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Total Amount:</strong> $<?= number_format($order['total_amount'], 2) ?></p>
                                            <?php if (!empty($order['transfer_slip'])): ?>
                                                <p class="mb-1"><strong>Previous Payment:</strong> <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#previousSlipModal">View</a></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <form method="post" enctype="multipart/form-data" id="paymentForm" onsubmit="return validateForm()">
                                    <h5 class="border-bottom pb-2 mb-3">Upload Payment Slip</h5>
                                    
                                    <!-- Error alert for client-side validation -->
                                    <div class="alert alert-danger alert-dismissible fade show d-none" id="clientErrorAlert" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-exclamation-octagon-fill fs-4 me-2"></i>
                                            <div>
                                                <strong>Error!</strong> <span id="clientErrorMessage">Please select a file before submitting.</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    
                                    <!-- Success alert (initially hidden) -->
                                    <div class="alert alert-success alert-dismissible fade show d-none" id="successAlert" role="alert">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                                            <div>
                                                <strong>Success!</strong> <span id="successMessage">Your payment slip has been resubmitted.</span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                    
                                    <div class="upload-container border border-2 border-dashed rounded-3 p-4 text-center mb-4" 
                                        style="border-color: rgba(0, 123, 255, 0.3); background: rgba(0, 123, 255, 0.03);">
                                        
                                        <input type="file" name="transfer_slip" class="form-control d-none" id="transferSlip" 
                                            accept="image/jpeg,image/png,image/jpg,application/pdf">
                                        
                                        <label for="transferSlip" class="mb-2 d-block" style="cursor: pointer;">
                                            <i class="bi bi-cloud-arrow-up fs-3 d-block mb-2 text-primary"></i>
                                            <span id="fileSelectionText">Click to select payment slip image or PDF</span>
                                        </label>
                                        
                                        <div id="previewContainer" class="text-center d-none mt-3">
                                            <!-- Image preview -->
                                            <div id="imagePreview" class="d-none">
                                                <div class="position-relative mb-2">
                                                    <img src="" id="previewImg" class="img-fluid rounded mx-auto d-block shadow-sm" 
                                                        style="max-height: 250px; max-width: 100%; border: 1px solid #dee2e6;">
                                                    <span class="position-absolute top-0 end-0 badge rounded-pill bg-primary m-2" id="previewBadge">
                                                        <i class="bi bi-arrow-repeat me-1"></i>Resubmission
                                                    </span>
                                                </div>
                                                <div class="mt-2 small text-muted">Image preview</div>
                                            </div>
                                            
                                            <!-- PDF preview -->
                                            <div id="pdfPreview" class="d-none py-4 bg-white rounded border shadow-sm" style="max-width: 200px; margin: 0 auto;">
                                                <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 48px;"></i>
                                                <p class="mt-2 mb-0 fw-semibold">PDF Document</p>
                                                <span class="badge rounded-pill bg-primary my-2" id="pdfPreviewBadge">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Resubmission
                                                </span>
                                                <div class="mt-1 small text-muted">PDF selected</div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-center gap-2 mt-3">
                                                <!-- View button for images -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="viewImageBtn">
                                                    <i class="bi bi-fullscreen"></i> View Larger
                                                </button>
                                                <!-- Remove button -->
                                                <button type="button" class="btn btn-sm btn-outline-danger" id="removeFileBtn">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div id="fileHint" class="form-text mt-2">Only JPEG, PNG or PDF files are accepted (max 5MB)</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <a href="orders.php" class="btn btn-outline-secondary w-100">
                                                <i class="bi bi-arrow-left me-2"></i>Cancel
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-primary w-100" id="submitBtn" onclick="submitWithAjax()">
                                                <i class="bi bi-send me-2"></i>Submit Payment
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($order['transfer_slip'])): ?>
<!-- Previous Slip Modal -->
<div class="modal fade" id="previousSlipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Previous Payment Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <?php 
                $file_ext = strtolower(pathinfo($order['transfer_slip'], PATHINFO_EXTENSION));
                if (in_array($file_ext, ['jpg', 'jpeg', 'png'])): 
                ?>
                    <img src="<?= $order['transfer_slip'] ?>" class="img-fluid rounded" alt="Previous payment slip">
                <?php elseif ($file_ext === 'pdf'): ?>
                    <div class="bg-light p-4 rounded">
                        <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 48px;"></i>
                        <p class="mt-2">PDF Document</p>
                        <a href="<?= $order['transfer_slip'] ?>" class="btn btn-sm btn-primary" target="_blank">
                            <i class="bi bi-eye me-1"></i>View PDF
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (required for file input validation) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Search functionality -->
<script src="assets/js/search.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload preview
    const fileInput = document.getElementById('transferSlip');
    const fileSelectionText = document.getElementById('fileSelectionText');
    const previewContainer = document.getElementById('previewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const pdfPreview = document.getElementById('pdfPreview');
    const previewImg = document.getElementById('previewImg');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const uploadContainer = document.querySelector('.upload-container');
    const errorAlert = document.getElementById('clientErrorAlert');
    
    // Initialize Bootstrap alert dismissal
    if (errorAlert) {
        const closeBtn = errorAlert.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                errorAlert.classList.add('d-none');
            });
        }
    }
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if (file) {
                // Hide error alert if visible
                if (errorAlert) {
                    errorAlert.classList.add('d-none');
                }
                
                fileSelectionText.textContent = 'Selected: ' + file.name;
                previewContainer.classList.remove('d-none');
                uploadContainer.style.borderColor = 'rgba(25, 135, 84, 0.5)';
                uploadContainer.style.background = 'rgba(25, 135, 84, 0.03)';
                
                try {
                    if (file.type.indexOf('image') > -1) {
                        // For images
                        imagePreview.classList.remove('d-none');
                        pdfPreview.classList.add('d-none');
                        
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    } 
                    else if (file.type === 'application/pdf') {
                        // For PDFs
                        imagePreview.classList.add('d-none');
                        pdfPreview.classList.remove('d-none');
                    }
                } catch(err) {
                    console.log('Preview error:', err);
                }
            }
        });
        
        // Remove file button
        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', function() {
                fileInput.value = '';
                previewContainer.classList.add('d-none');
                fileSelectionText.textContent = 'Click to select payment slip image or PDF';
                uploadContainer.style.borderColor = 'rgba(0, 123, 255, 0.3)';
                uploadContainer.style.background = 'rgba(0, 123, 255, 0.03)';
            });
        }
    }
    
    // Toggle sidebar on mobile
    window.toggleSidebar = function() {
        document.querySelector('.sidebar').classList.toggle('active');
        document.querySelector('.main-content').classList.toggle('active');
        document.querySelector('.overlay').classList.toggle('active');
    };
    
    // Form validation
    window.validateForm = function() {
        const fileInput = document.getElementById('transferSlip');
        const errorAlert = document.getElementById('clientErrorAlert');
        const errorMessage = document.getElementById('clientErrorMessage');
        const uploadContainer = document.querySelector('.upload-container');
        
        // Reset animation classes
        errorAlert.classList.remove('animate__animated', 'animate__shakeX');
        uploadContainer.classList.remove('animate__animated', 'animate__headShake');
        
        // Check if file is selected
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            // Show error message with animation
            errorAlert.classList.remove('d-none');
            void errorAlert.offsetWidth; // Trigger reflow to restart animation
            errorAlert.classList.add('animate__animated', 'animate__shakeX');
            errorMessage.textContent = 'Please select a file before submitting.';
            
            // Animate the upload container
            uploadContainer.classList.add('animate__animated', 'animate__headShake');
            uploadContainer.style.borderColor = 'rgba(220, 53, 69, 0.5)';
            uploadContainer.style.background = 'rgba(220, 53, 69, 0.05)';
            
            // Scroll to the error message
            errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Focus on the file input after a short delay
            setTimeout(() => {
                // Reset the upload container style after animation
                uploadContainer.style.borderColor = 'rgba(0, 123, 255, 0.3)';
                uploadContainer.style.background = 'rgba(0, 123, 255, 0.03)';
                
                // Trigger click on the label to open file dialog
                document.querySelector('label[for="transferSlip"]').click();
            }, 1000);
            
            return false;
        }
        
        // File type validation
        const file = fileInput.files[0];
        const fileType = file.type;
        if (!fileType.match(/image\/(jpeg|jpg|png)/) && fileType !== 'application/pdf') {
            errorAlert.classList.remove('d-none');
            void errorAlert.offsetWidth; // Trigger reflow to restart animation
            errorAlert.classList.add('animate__animated', 'animate__shakeX');
            errorMessage.textContent = 'Only JPG, PNG and PDF files are allowed.';
            return false;
        }
        
        // File size validation (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            errorAlert.classList.remove('d-none');
            void errorAlert.offsetWidth; // Trigger reflow to restart animation
            errorAlert.classList.add('animate__animated', 'animate__shakeX');
            errorMessage.textContent = 'File size should not exceed 5MB.';
            return false;
        }
        
        return true;
    };

    // View Image Button handler
    const viewImageBtn = document.getElementById('viewImageBtn');
    if (viewImageBtn) {
        viewImageBtn.addEventListener('click', function() {
            const previewImg = document.getElementById('previewImg');
            if (previewImg && previewImg.src) {
                // Create a modal dynamically
                createImagePreviewModal(previewImg.src);
            }
        });
    }

    // Create image preview modal
    function createImagePreviewModal(imgSrc) {
        // Remove any existing modal
        const existingModal = document.getElementById('imagePreviewModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal HTML
        const modalHTML = `
            <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Payment Slip Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imgSrc}" class="img-fluid rounded" alt="Payment slip preview" style="max-height: 70vh;">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to the document
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        modal.show();
    }
    
    // AJAX submission function
    window.submitWithAjax = function() {
        // Validate form first
        if (!validateForm()) {
            return false;
        }
        
        const form = document.getElementById('paymentForm');
        const formData = new FormData(form);
        
        // Add order_id to the form data
        formData.append('order_id', <?= $order_id ?>);
        
        // Show loading state on button
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Uploading...';
        }
        
        // Hide any visible alerts
        document.getElementById('clientErrorAlert').classList.add('d-none');
        document.getElementById('successAlert').classList.add('d-none');
        
        // Send AJAX request
        fetch('api/orders/resubmit_payment.php', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                const successAlert = document.getElementById('successAlert');
                const successMessage = document.getElementById('successMessage');
                successMessage.textContent = data.message || 'Payment slip resubmitted successfully.';
                successAlert.classList.remove('d-none');
                successAlert.classList.add('animate__animated', 'animate__fadeIn');
                
                // Scroll to success message
                successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Reset form and preview
                document.getElementById('removeFileBtn').click();
                
                // Redirect after a delay
                setTimeout(() => {
                    window.location.href = 'orders.php?success=1&message=' + encodeURIComponent('Your payment has been resubmitted successfully.');
                }, 3000);
            } else {
                // Show error message
                const errorAlert = document.getElementById('clientErrorAlert');
                const errorMessage = document.getElementById('clientErrorMessage');
                errorMessage.textContent = data.message || 'An error occurred. Please try again.';
                errorAlert.classList.remove('d-none');
                errorAlert.classList.add('animate__animated', 'animate__shakeX');
                
                // Reset button
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Payment';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            
            // Show error message
            const errorAlert = document.getElementById('clientErrorAlert');
            const errorMessage = document.getElementById('clientErrorMessage');
            errorMessage.textContent = 'Network error. Please check your connection and try again.';
            errorAlert.classList.remove('d-none');
            
            // Reset button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send me-2"></i>Submit Payment';
            }
        });
    };
});
</script>

</body>
</html> 