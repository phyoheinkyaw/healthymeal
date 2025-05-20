<?php
session_start();
require_once 'config/connection.php';

// Get post ID
$post_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$post_id) {
    header("Location: blog.php");
    exit();
}

// Fetch blog post with author info
$stmt = $mysqli->prepare("
    SELECT bp.*, u.full_name as author_name
    FROM blog_posts bp
    LEFT JOIN users u ON bp.author_id = u.user_id
    WHERE bp.post_id = ?
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header("Location: blog.php");
    exit();
}

// Fetch comments
$stmt = $mysqli->prepare("
    SELECT c.*, u.full_name as commenter_name
    FROM comments c
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE c.post_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$comments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Blog Post -->
            <article class="blog-post">
                <?php if (isset($post['image_url']) && !empty($post['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" 
                         class="img-fluid rounded mb-4" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>">
                <?php else: ?>
                    <img src="https://placehold.co/1080x300/FFF3E6/FF6B35?text=<?php echo htmlspecialchars($post['title']); ?>" 
                         class="img-fluid rounded mb-4" 
                         alt="Blog post placeholder">
                <?php endif; ?>

                <h1 class="mb-4"><?php echo htmlspecialchars($post['title']); ?></h1>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="author">
                        <i class="bi bi-person"></i>
                        <span class="text-muted"><?php echo htmlspecialchars($post['author_name']); ?></span>
                    </div>
                    <div class="date">
                        <i class="bi bi-calendar"></i>
                        <span class="text-muted">
                            <?php echo date('F d, Y', strtotime($post['created_at'])); ?>
                        </span>
                    </div>
                </div>

                <div class="blog-content mb-5">
                    <?php echo $post['content']; ?>
                </div>
            </article>

            <!-- Comments Section -->
            <section class="comments-section">
                <h3 class="mb-4">Comments</h3>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Comment Form -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form id="commentForm">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Your Comment</label>
                                    <textarea class="form-control" id="comment" name="content" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Post Comment</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Please <a href="login.php">login</a> to post a comment.
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div id="commentsList">
                    <?php if ($comments->num_rows > 0): ?>
                        <?php while ($comment = $comments->fetch_assoc()): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle text-muted">
                                            <?php echo htmlspecialchars($comment['commenter_name']); ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-light text-center">
                            No comments yet. Be the first to comment!
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const commentForm = document.getElementById('commentForm');
    const commentsList = document.getElementById('commentsList');
    
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            fetch('api/blog/add_comment.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear the comment field
                    document.getElementById('comment').value = '';
                    
                    // Refresh comments without full page reload
                    refreshComments();
                } else {
                    alert(data.message || 'Failed to post comment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    }
    
    // Function to refresh comments
    function refreshComments() {
        fetch(`api/blog/get_comments.php?post_id=<?php echo $post_id; ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Build new comments HTML
                let commentsHTML = '';
                
                if (data.comments.length > 0) {
                    data.comments.forEach(comment => {
                        commentsHTML += `
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-subtitle text-muted">
                                            ${comment.commenter_name}
                                        </h6>
                                        <small class="text-muted">
                                            ${comment.created_at}
                                        </small>
                                    </div>
                                    <p class="card-text">${comment.content.replace(/\n/g, '<br>')}</p>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    commentsHTML = `
                        <div class="alert alert-light text-center">
                            No comments yet. Be the first to comment!
                        </div>
                    `;
                }
                
                // Update the comments list
                commentsList.innerHTML = commentsHTML;
            }
        })
        .catch(error => {
            console.error('Error refreshing comments:', error);
        });
    }
});
</script>

</body>
</html> 