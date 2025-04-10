<?php
session_start();
require_once 'config/connection.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Healthy Meal Kit</title>
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
        <!-- Hero Section -->
        <div class="row mb-5">
            <div class="col-md-6">
                <h1 class="display-4 mb-4">About Healthy Meal Kit</h1>
                <p class="lead">We're on a mission to make healthy eating easy, delicious, and accessible to everyone.
                </p>
                <p>Our meal kits are designed by expert nutritionists and chefs to provide you with balanced, nutritious
                    meals that don't compromise on taste. We believe that eating healthy shouldn't be a chore - it
                    should be an enjoyable experience that brings people together.</p>
            </div>
            <div class="col-md-6">
                <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?q=80&w=2070&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"
                    alt="About Us" class="img-fluid rounded shadow">
            </div>
        </div>

        <!-- Our Values -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="text-center mb-4">Our Values</h2>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-heart-fill text-primary-custom display-4 mb-3"></i>
                        <h4>Health First</h4>
                        <p>We prioritize nutritional value and balance in every meal kit we create.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-globe text-primary-custom display-4 mb-3"></i>
                        <h4>Sustainability</h4>
                        <p>We source ingredients responsibly and use eco-friendly packaging materials.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill text-primary-custom display-4 mb-3"></i>
                        <h4>Community</h4>
                        <p>We believe in building a community of health-conscious food lovers.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Our Team -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="text-center mb-4">Meet Our Team</h2>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=John+Doe" class="card-img-top"
                        alt="Team member">
                    <div class="card-body text-center">
                        <h5 class="card-title">John Doe</h5>
                        <p class="card-text text-muted">Head Chef</p>
                        <div class="social-links">
                            <a href="#" class="text-dark me-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-dark me-2"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-dark"><i class="bi bi-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=Jane+Smith" class="card-img-top"
                        alt="Team member">
                    <div class="card-body text-center">
                        <h5 class="card-title">Jane Smith</h5>
                        <p class="card-text text-muted">Nutritionist</p>
                        <div class="social-links">
                            <a href="#" class="text-dark me-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-dark me-2"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-dark"><i class="bi bi-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=Mike+Johnson" class="card-img-top"
                        alt="Team member">
                    <div class="card-body text-center">
                        <h5 class="card-title">Mike Johnson</h5>
                        <p class="card-text text-muted">Operations Manager</p>
                        <div class="social-links">
                            <a href="#" class="text-dark me-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-dark me-2"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-dark"><i class="bi bi-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <img src="https://placehold.co/600x400/FFF3E6/FF6B35?text=Sarah+Wilson" class="card-img-top"
                        alt="Team member">
                    <div class="card-body text-center">
                        <h5 class="card-title">Sarah Wilson</h5>
                        <p class="card-text text-muted">Customer Success</p>
                        <div class="social-links">
                            <a href="#" class="text-dark me-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-dark me-2"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-dark"><i class="bi bi-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="row">
            <div class="col-12">
                <h2 class="text-center mb-4">Get in Touch</h2>
            </div>
            <div class="col-md-6 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form id="contactForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->

</body>

</html>