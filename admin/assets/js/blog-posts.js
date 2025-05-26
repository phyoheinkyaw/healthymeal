$(document).ready(function() {
    // Global variables
    let currentPostId = null;
    let currentCommentId = null;

    // Initialize Summernote rich text editor
    $('#postContent').summernote({
        placeholder: 'Write your blog post content here...',
        height: 300,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onImageUpload: function(files) {
                // For this version, we'll just warn that direct uploads aren't supported
                showAlert('warning', 'Direct image uploads are not supported. Please use image URLs instead.');
            }
        }
    });

    // Initialize DataTable with consistent styling
    /*
    const blogPostsTable = $('#blogPostsTable').DataTable({
        responsive: true,
        order: [[0, 'desc']], // Default order by ID descending
        language: {
            search: "Search:",
            searchPlaceholder: "Search blog posts...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ posts",
            infoEmpty: "No posts found",
            emptyTable: "No blog posts available",
            paginate: {
                first: '<i class="bi bi-chevron-double-left"></i>',
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>',
                last: '<i class="bi bi-chevron-double-right"></i>'
            }
        },
        columnDefs: [
            { orderable: false, targets: 6 } // Disable sorting on actions column
        ]
    });
    */
    
    // Reference to the existing DataTable
    const blogPostsTable = $('#blogPostsTable').DataTable();

    // Load initial blog posts
    loadBlogPosts();

    // Form submission handlers
    $('#savePostBtn').on('click', function() {
        savePost();
    });

    // Handle delete button click
    $(document).on('click', '.delete-post', function() {
        const postId = $(this).data('id');
        const postTitle = $(this).data('title');
        $('#deleteItemName').text(postTitle);
        
        currentPostId = postId;
        $('#deleteConfirmModal').modal('show');
    });

    // Handle edit button click
    $(document).on('click', '.edit-post', function() {
        const postId = $(this).data('id');
        editPost(postId);
    });

    // Handle view button click
    $(document).on('click', '.view-post', function() {
        const postId = $(this).data('id');
        viewPost(postId);
    });

    // Edit button in view modal
    $('#editPostBtn').on('click', function() {
        $('#viewPostModal').modal('hide');
        editPost(currentPostId);
    });

    // Handle confirm delete button click
    $('#confirmDeleteBtn').on('click', function() {
        deletePost(currentPostId);
    });

    // Handle comment delete
    $(document).on('click', '.delete-comment', function() {
        currentCommentId = $(this).data('id');
        $('#deleteCommentModal').modal('show');
    });
    
    // Confirm comment delete
    $('#confirmDeleteCommentBtn').on('click', function() {
        deleteComment(currentCommentId);
    });

    // Reset form when modal is closed
    $('#addPostModal').on('hidden.bs.modal', function() {
        resetPostForm();
    });

    // HELPER FUNCTIONS

    // Load all blog posts
    function loadBlogPosts() {
        $.ajax({
            url: 'api/blog/get_all.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Clear existing table data
                    blogPostsTable.clear();
                    
                    if (response.posts && response.posts.length > 0) {
                        response.posts.forEach(function(post) {
                            blogPostsTable.row.add([
                                post.post_id,
                                post.image_url ? 
                                    `<img src="${post.image_url}" class="blog-post-thumbnail" alt="${post.title}">` : 
                                    `<img src="https://placehold.co/120x90/FFF3E6/FF6B35?text=${encodeURIComponent(post.title.substring(0, 10))}" class="blog-post-thumbnail" alt="No image">`,
                                `<div class="fw-bold">${post.title}</div>`,
                                '<div class="content-preview">' + post.content_preview + '</div>',
                                post.author_name,
                                '<span class="badge bg-info">' + post.comment_count + '</span>',
                                post.created_at,
                                `<div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-info view-post" data-id="${post.post_id}" title="View">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning edit-post" data-id="${post.post_id}" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-post" 
                                            data-id="${post.post_id}" 
                                            data-title="${post.title.replace(/"/g, '&quot;')}" 
                                            title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>`
                            ]).draw(false);
                        });
                    } else {
                        showAlert('info', 'No blog posts found. Create your first post by clicking the "Create New Post" button.');
                    }
                } else {
                    showAlert('danger', 'Error loading blog posts: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                showAlert('danger', 'Failed to load blog posts. Please try again later.');
                console.error('Error fetching blog posts:', error);
            }
        });
    }

    // Save blog post (create or update)
    function savePost() {
        // Get form data
        const postId = $('#postId').val();
        const title = $('#postTitle').val().trim();
        const content = $('#postContent').summernote('code');
        const imageUrl = $('#postImageUrl').val().trim();
        const imageFile = document.getElementById('imageFile').files[0];
        const isFileUpload = document.getElementById('toggleImageInput').checked;

        // Validate form
        if (!title) {
            showAlert('danger', 'Title is required');
            return;
        }

        if (!content || content === '<p><br></p>') {
            showAlert('danger', 'Content is required');
            return;
        }

        // Create FormData object for file upload support
        const formData = new FormData();
        formData.append('title', title);
        formData.append('content', content);
        
        // Handle image (either URL or file)
        if (isFileUpload && imageFile) {
            formData.append('image_file', imageFile);
        } else if (imageUrl) {
            formData.append('image_url', imageUrl);
        }

        if (postId) {
            formData.append('post_id', postId);
        }

        // Show loading indicator on the save button
        const $saveBtn = $('#savePostBtn');
        const originalText = $saveBtn.html();
        $saveBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        $saveBtn.prop('disabled', true);

        // Send AJAX request
        $.ajax({
            url: postId ? 'api/blog/update.php' : 'api/blog/create_post.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Reset button state
                $saveBtn.html(originalText);
                $saveBtn.prop('disabled', false);

                if (response.success) {
                    // Close modal
                    $('#addPostModal').modal('hide');
                    
                    // Show success message
                    showAlert('success', response.message);
                    
                    // Reload blog posts
                    loadBlogPosts();
                } else {
                    showAlert('danger', 'Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Reset button state
                $saveBtn.html(originalText);
                $saveBtn.prop('disabled', false);
                
                console.error('Error saving blog post:', error);
                showAlert('danger', 'Failed to save blog post. Please try again.');
            }
        });
    }

    // View blog post
    function viewPost(postId) {
        // Store current post ID
        currentPostId = postId;

        $.ajax({
            url: 'api/blog/get_post.php',
            type: 'GET',
            data: { id: postId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const post = response.post;
                    
                    // Populate view modal
                    $('#viewPostTitleContent').text(post.title);
                    $('#viewPostAuthor').text('By ' + post.author_name);
                    $('#viewPostDate').text(post.created_at);
                    $('#viewPostContent').html(post.content);
                    
                    // Display image if available
                    if (post.image_url) {
                        $('#viewPostImage').attr('src', post.image_url).removeClass('d-none');
                    } else {
                        $('#viewPostImage').addClass('d-none');
                    }
                    
                    // Populate comments
                    const comments = post.comments || [];
                    $('#commentCount').text(comments.length);
                    
                    const commentsContainer = $('#commentsContainer');
                    commentsContainer.empty();
                    
                    if (comments.length === 0) {
                        commentsContainer.html('<p class="text-muted">No comments yet.</p>');
                    } else {
                        comments.forEach(function(comment) {
                            commentsContainer.append(`
                                <div class="comment-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="comment-meta">
                                            <strong>${comment.username}</strong> • ${comment.created_at}
                                        </div>
                                        <button type="button" class="btn btn-sm text-danger delete-comment" data-id="${comment.comment_id}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="comment-content">${comment.content}</div>
                                </div>
                            `);
                        });
                    }
                    
                    // Show modal
                    $('#viewPostModal').modal('show');
                } else {
                    showAlert('danger', 'Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                showAlert('danger', 'Failed to load blog post details.');
                console.error('Error viewing post:', error);
            }
        });
    }

    // Edit blog post
    function editPost(postId) {
        $.ajax({
            url: 'api/blog/get_post.php',
            type: 'GET',
            data: { id: postId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const post = response.post;
                    
                    // Set form values
                    $('#postId').val(post.post_id);
                    $('#postTitle').val(post.title);
                    $('#postContent').summernote('code', post.content);
                    
                    // Handle image
                    const toggle = document.getElementById('toggleImageInput');
                    const urlInput = document.getElementById('postImageUrl');
                    const imagePreview = document.getElementById('imagePreview');
                    const previewWrapper = document.getElementById('imagePreviewWrapper');
                    
                    // Reset file input
                    document.getElementById('imageFile').value = '';
                    
                    // Set image URL and show preview if available
                    if (post.image_url) {
                        // Make sure toggle is off (URL mode)
                        toggle.checked = false;
                        document.getElementById('imageUrlInputWrapper').classList.remove('d-none');
                        document.getElementById('imageFileInputWrapper').classList.add('d-none');
                        
                        // Set URL and preview
                        urlInput.value = post.image_url;
                        imagePreview.src = post.image_url;
                        previewWrapper.style.display = '';
                    } else {
                        // No image, hide preview
                        urlInput.value = '';
                        previewWrapper.style.display = 'none';
                    }
                    
                    // Update modal title
                    $('#postModalTitle').text('Edit Blog Post');
                    
                    // Show modal
                    $('#addPostModal').modal('show');
                } else {
                    showAlert('danger', 'Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                showAlert('danger', 'Failed to load blog post for editing.');
                console.error('Error editing post:', error);
            }
        });
    }

    // Delete blog post
    function deletePost(postId) {
        if (!postId) return;
        
        $.ajax({
            url: 'api/blog/delete.php',
            type: 'POST',
            data: { post_id: postId },
            dataType: 'json',
            success: function(response) {
                // Close the confirmation modal
                $('#deleteConfirmModal').modal('hide');
                
                if (response.success) {
                    // Show success message
                    showAlert('success', 'Blog post deleted successfully');
                    
                    // Reload blog posts list
                    loadBlogPosts();
                } else {
                    showAlert('danger', 'Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Close the confirmation modal
                $('#deleteConfirmModal').modal('hide');
                
                showAlert('danger', 'Failed to delete blog post.');
                console.error('Error deleting post:', error);
            }
        });
    }

    // Delete comment
    function deleteComment(commentId) {
        if (!commentId) return;
        
        $.ajax({
            url: 'api/blog/delete_comment.php',
            type: 'POST',
            data: { comment_id: commentId },
            dataType: 'json',
            success: function(response) {
                // Close the confirmation modal
                $('#deleteCommentModal').modal('hide');
                
                if (response.success) {
                    // Show success message
                    showAlert('success', 'Comment deleted successfully');
                    
                    // Reload current post view
                    viewPost(currentPostId);
                    
                    // Also refresh the blog posts list (to update comment counts)
                    loadBlogPosts();
                } else {
                    showAlert('danger', 'Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                // Close the confirmation modal
                $('#deleteCommentModal').modal('hide');
                
                showAlert('danger', 'Failed to delete comment.');
                console.error('Error deleting comment:', error);
            }
        });
    }

    // Reset post form
    function resetPostForm() {
        $('#postForm')[0].reset();
        $('#postId').val('');
        $('#postContent').summernote('code', '');
        $('#postModalTitle').text('Create New Blog Post');
    }

    // Show alert
    function showAlert(type, message) {
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#alertContainer').html(alertHTML);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
}); 