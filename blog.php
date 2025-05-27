<?php
session_start();
require_once 'config/connection.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Get total posts count
$total_posts = $mysqli->query("SELECT COUNT(*) as count FROM blog_posts")->fetch_assoc()['count'];
$total_pages = ceil($total_posts / $per_page);

// Fetch blog posts with author info
$posts = $mysqli->query("
    SELECT bp.*, u.full_name as author_name,
           (SELECT COUNT(*) FROM comments WHERE post_id = bp.post_id) as comment_count
    FROM blog_posts bp
    LEFT JOIN users u ON bp.author_id = u.user_id
    ORDER BY bp.created_at DESC
    LIMIT $offset, $per_page
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Healthy Meal Kit</title>
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
        <h1 class="text-center mb-5">Our Blog</h1>

        <div class="row g-4">
            <?php while ($post = $posts->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <?php if (isset($post['image_url']) && !empty($post['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" class="card-img-top"
                        alt="<?php echo htmlspecialchars($post['title']); ?>">
                    <?php else: ?>
                    <img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=<?php echo htmlspecialchars($post['title']); ?>"
                        class="card-img-top" alt="Blog post placeholder">
                    <?php endif; ?>

                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                        <p class="card-text">
                            <?php echo substr(strip_tags($post['content']), 0, 150) . '...'; ?>
                        </p>
                    </div>

                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($post['author_name']); ?>
                            </small>
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i>
                                <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                            </small>
                            <small class="text-muted">
                                <i class="bi bi-chat"></i>
                                <?php echo $post['comment_count']; ?> comments
                            </small>
                        </div>
                        <div class="text-center mt-3">
                            <a href="blog-post.php?id=<?php echo $post['post_id']; ?>"
                                class="btn btn-primary btn-sm">Read More</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Blog pagination" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>

</body>

</html>