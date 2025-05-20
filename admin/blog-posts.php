<?php
require_once '../includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// Redirect non-admin users
if (!$role || $role != 1) {
    header("Location: /hm/login.php");
    exit();
}

// Ensure user_id is set in session
if (!isset($_SESSION['user_id'])) {
    // This is a fallback in case checkRememberToken doesn't set the session correctly
    http_response_code(400);
    echo "User session is not properly initialized. Please log out and log in again.";
    exit();
}

// Get flash message if exists
$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .content-preview {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .post-actions {
            min-width: 120px;
        }
        
        .summernote-container {
            min-height: 300px;
        }
        
        #postContent {
            min-height: 250px;
        }
        
        .comment-item {
            border-left: 3px solid #dee2e6;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .comment-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .comment-content {
            margin-top: 5px;
        }
        
        .blog-post-thumbnail {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .btn-group .btn {
            border-radius: 4px;
            margin-right: 2px;
        }
        
        .alert-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-box.fade-out {
            animation: slideOut 0.3s ease-in;
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        
        <main class="main-content">
            <div class="container-fluid p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Blog Management</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Blog Posts</li>
                            </ol>
                        </nav>
                    </div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPostModal">
                        <i class="bi bi-plus-lg"></i> Create New Post
                    </button>
                </div>

                <?php if ($flash_message): ?>
                <div class="alert alert-<?= $flash_message['type'] ?> alert-dismissible fade show" role="alert">
                    <?= $flash_message['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Alert container for dynamic messages -->
                <div id="alertContainer" class="mb-4"></div>

                <!-- Blog Posts Table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="blogPostsTable">
                                <thead>
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="10%">Image</th>
                                        <th width="20%">Title</th>
                                        <th width="25%">Content Preview</th>
                                        <th width="10%">Author</th>
                                        <th width="10%">Comments</th>
                                        <th width="10%">Date</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Blog posts will be loaded here via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Post Modal -->
    <div class="modal fade" id="addPostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="postModalTitle">Create New Blog Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="postForm">
                        <input type="hidden" id="postId" name="post_id">
                        <div class="mb-3">
                            <label for="postTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="postTitle" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="toggleImageInput">
                                        <label class="form-check-label" for="toggleImageInput">Upload (Click to change)</label>
                                    </div>
                                </div>
                                <div id="imageUrlInputWrapper" class="flex-grow-1">
                                    <input type="url" class="form-control" id="postImageUrl" name="image_url" placeholder="Paste image URL or upload" autocomplete="off">
                                </div>
                                <div id="imageFileInputWrapper" class="flex-grow-1 d-none">
                                    <input type="file" class="form-control" id="imageFile" name="imageFile" accept="image/*">
                                </div>
                                <div id="imagePreviewWrapper" class="mt-2" style="display:none;">
                                    <label class="form-label">Preview:</label>
                                    <div>
                                        <img id="imagePreview" src="#" alt="Image preview" class="img-thumbnail" style="max-width: 240px; max-height: 180px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="postContent" class="form-label">Content</label>
                            <div class="summernote-container">
                                <textarea class="form-control" id="postContent" name="content" rows="10" required></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="savePostBtn">Save Post</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Post Modal -->
    <div class="modal fade" id="viewPostModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPostTitle">View Blog Post</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h4 id="viewPostTitleContent"></h4>
                        <div class="text-muted small">
                            <span id="viewPostAuthor"></span> • 
                            <span id="viewPostDate"></span>
                        </div>
                    </div>
                    <img id="viewPostImage" class="img-fluid rounded mb-3 d-none" alt="Blog post image">
                    <div class="mb-4" id="viewPostContent"></div>
                    
                    <hr>
                    
                    <h5 class="mb-3">Comments (<span id="commentCount">0</span>)</h5>
                    <div id="commentsContainer">
                        <!-- Comments will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="editPostBtn">
                        <i class="bi bi-pencil"></i> Edit Post
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger-subtle">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Delete Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<span id="deleteItemName"></span>"?</p>
                    <p class="text-danger"><strong>This action cannot be undone and will also delete all associated comments.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Comment Confirmation Modal -->
    <div class="modal fade" id="deleteCommentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger-subtle">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Delete Comment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this comment?</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteCommentBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <!-- Common Admin JS -->
    <script src="assets/js/admin.js"></script>
    <!-- Page Specific JS -->
    <script src="assets/js/blog-posts.js"></script>
    
    <script>
    // Initialize DataTable
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable only if it hasn't been initialized already
        if (!$.fn.DataTable.isDataTable('#blogPostsTable')) {
            const table = $('#blogPostsTable').DataTable({
                order: [[0, 'desc']],
                responsive: true,
                language: {
                    search: "Search blog posts:",
                    lengthMenu: "Show _MENU_ posts per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ posts",
                    emptyTable: "No blog posts available"
                }
            });
        }
        
        // Image upload toggle and preview
        const toggle = document.getElementById('toggleImageInput');
        const urlWrapper = document.getElementById('imageUrlInputWrapper');
        const fileWrapper = document.getElementById('imageFileInputWrapper');
        const urlInput = document.getElementById('postImageUrl');
        const fileInput = document.getElementById('imageFile');
        const previewWrapper = document.getElementById('imagePreviewWrapper');
        const previewImg = document.getElementById('imagePreview');

        function showPreview(src) {
            if (src) {
                previewImg.src = src;
                previewWrapper.style.display = '';
            } else {
                previewImg.src = '#';
                previewWrapper.style.display = 'none';
            }
        }

        // Toggle logic
        if (toggle && urlWrapper && fileWrapper) {
            toggle.addEventListener('change', function() {
                if (toggle.checked) {
                    urlWrapper.classList.add('d-none');
                    fileWrapper.classList.remove('d-none');
                    showPreview('');
                } else {
                    fileWrapper.classList.add('d-none');
                    urlWrapper.classList.remove('d-none');
                    showPreview(urlInput.value);
                }
            });
        }

        // Preview for URL
        if (urlInput) {
            urlInput.addEventListener('input', function() {
                if (urlInput.value && !toggle.checked) {
                    showPreview(urlInput.value);
                } else {
                    showPreview('');
                }
            });
        }

        // Preview for file upload
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (fileInput.files && fileInput.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        showPreview(e.target.result);
                    };
                    reader.readAsDataURL(fileInput.files[0]);
                } else {
                    showPreview('');
                }
            });
        }
    });
    </script>
</body>
</html> 