<?php
require_once 'includes/auth_check.php';

// Check for remember me token and get user role
$role = checkRememberToken();

// If user is logged in, set session variables
if ($role) {
    // Set session variables if not already set
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $role === 'admin' ? 1 : $role;
        $_SESSION['role'] = $role;
    }
}

// Fetch all health tips
$tips_query = "SELECT * FROM health_tips ORDER BY created_at DESC";
$tips_result = $mysqli->query($tips_query);

// Fetch all ingredients with nutritional information
$ingredients_query = "SELECT * FROM ingredients ORDER BY name ASC";
$ingredients_result = $mysqli->query($ingredients_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutritional Information & Health Tips - Healthy Meal Kit</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Owl Carousel CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    .nutrition-card {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        height: 100%;
        transition: transform 0.3s ease;
    }

    .nutrition-card:hover {
        transform: translateY(-5px);
    }

    .nutrition-header {
        background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
        color: white;
        padding: 15px;
        font-weight: bold;
    }

    .nutrition-body {
        padding: 20px;
    }

    .nutrition-value {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary);
    }

    .nutrition-label {
        font-size: 0.9rem;
        color: #6c757d;
    }

    .health-tip-card {
        background-color: var(--light);
        border-radius: 15px;
        padding: 30px;
        margin: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        position: relative;
    }

    .health-tip-card::before {
        content: '"';
        position: absolute;
        top: 10px;
        left: 20px;
        font-size: 60px;
        color: rgba(255, 107, 53, 0.2);
        font-family: serif;
    }

    .health-tip-content {
        font-size: 1.1rem;
        line-height: 1.6;
        font-style: italic;
        color: var(--dark);
        padding-left: 20px;
    }

    .owl-nav button {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: var(--primary) !important;
        color: white !important;
        width: 40px;
        height: 40px;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .owl-nav button.owl-prev {
        left: -20px;
    }

    .owl-nav button.owl-next {
        right: -20px;
    }

    .owl-dots button.owl-dot.active span {
        background-color: var(--primary) !important;
    }

    .section-title {
        position: relative;
        display: inline-block;
        margin-bottom: 30px;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        width: 60px;
        height: 3px;
        background-color: var(--primary);
    }

    .dietary-tag {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        margin-right: 5px;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .tag-vegetarian {
        background-color: #E8F5E9;
        color: #2E7D32;
    }

    .tag-vegan {
        background-color: #F1F8E9;
        color: #558B2F;
    }

    .tag-halal {
        background-color: #E3F2FD;
        color: #1565C0;
    }

    .ingredient-search {
        border-radius: 30px;
        padding: 10px 20px;
        border: 2px solid var(--light);
        box-shadow: none;
        margin-bottom: 30px;
    }

    .ingredient-search:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
    }
    </style>
</head>

<body>

    <?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <section class="py-5 bg-primary-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="mb-3">Nutritional Information & Health Tips</h1>
                    <p class="lead">Discover the nutritional value of our ingredients and learn valuable health tips to
                        improve your well-being.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Health Tips Carousel -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="section-title">Health Tips</h2>
                    <p>Swipe through our collection of health tips to learn more about maintaining a healthy lifestyle.
                    </p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="owl-carousel health-tips-carousel owl-theme">
                        <?php while($tip = $tips_result->fetch_assoc()): ?>
                        <div class="item">
                            <div class="health-tip-card">
                                <p class="health-tip-content"><?php echo htmlspecialchars($tip['content']); ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Nutritional Information -->
    <section class="py-5 bg-primary-light">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="section-title">Nutritional Information</h2>
                    <p>Explore the nutritional values of ingredients used in our meal kits.</p>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6 mx-auto">
                    <input type="text" id="ingredientSearch" class="form-control ingredient-search"
                        placeholder="Search for an ingredient...">
                </div>
            </div>

            <div class="row" id="ingredientsContainer">
                <?php while($ingredient = $ingredients_result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4 ingredient-item">
                    <div class="nutrition-card">
                        <div class="nutrition-header">
                            <h5 class="mb-0"><?php echo htmlspecialchars($ingredient['name']); ?></h5>
                        </div>
                        <div class="nutrition-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="nutrition-label">Calories</div>
                                    <div class="nutrition-value"><?php echo $ingredient['calories_per_100g']; ?> cal
                                    </div>
                                    <div class="small text-muted">per 100g</div>
                                </div>
                                <div class="col-6">
                                    <div class="nutrition-label">Price</div>
                                    <div class="nutrition-value">
                                        $<?php echo number_format($ingredient['price_per_100g'], 2); ?></div>
                                    <div class="small text-muted">per 100g</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-4">
                                    <div class="nutrition-label">Protein</div>
                                    <div class="nutrition-value"><?php echo $ingredient['protein_per_100g']; ?>g</div>
                                </div>
                                <div class="col-4">
                                    <div class="nutrition-label">Carbs</div>
                                    <div class="nutrition-value"><?php echo $ingredient['carbs_per_100g']; ?>g</div>
                                </div>
                                <div class="col-4">
                                    <div class="nutrition-label">Fat</div>
                                    <div class="nutrition-value"><?php echo $ingredient['fat_per_100g']; ?>g</div>
                                </div>
                            </div>

                            <!-- <div class="dietary-tags mt-3">
                            <?php if($ingredient['is_vegetarian']): ?>
                                <span class="dietary-tag tag-vegetarian">Vegetarian</span>
                            <?php endif; ?>
                            
                            <?php if($ingredient['is_vegan']): ?>
                                <span class="dietary-tag tag-vegan">Vegan</span>
                            <?php endif; ?>
                            
                            <?php if($ingredient['is_halal']): ?>
                                <span class="dietary-tag tag-halal">Halal</span>
                            <?php endif; ?>
                        </div> -->
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Nutritional Guidelines -->
    <section class="py-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="section-title">Nutritional Guidelines</h2>
                    <p>Understanding your nutritional needs is essential for maintaining a healthy lifestyle.</p>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="card-title text-primary-custom">Daily Caloric Needs</h4>
                            <p class="card-text">The average adult needs between 1,600 to 3,000 calories per day,
                                depending on physical activity, age, and gender.</p>
                            <ul>
                                <li>Sedentary adults: 1,600-2,000 calories</li>
                                <li>Moderately active adults: 2,000-2,500 calories</li>
                                <li>Active adults: 2,500-3,000 calories</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="card-title text-primary-custom">Macronutrient Balance</h4>
                            <p class="card-text">A balanced diet typically includes the following macronutrient
                                distribution:</p>
                            <ul>
                                <li>Carbohydrates: 45-65% of daily calories</li>
                                <li>Proteins: 10-35% of daily calories</li>
                                <li>Fats: 20-35% of daily calories</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="card-title text-primary-custom">Hydration</h4>
                            <p class="card-text">Staying hydrated is crucial for overall health. The general
                                recommendation is:</p>
                            <ul>
                                <li>Men: About 3.7 liters (15.5 cups) of fluids per day</li>
                                <li>Women: About 2.7 liters (11.5 cups) of fluids per day</li>
                            </ul>
                            <p>This includes water from all beverages and foods.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h4 class="card-title text-primary-custom">Dietary Fiber</h4>
                            <p class="card-text">Fiber is essential for digestive health and can help prevent various
                                diseases.</p>
                            <ul>
                                <li>Men: 30-38 grams per day</li>
                                <li>Women: 21-25 grams per day</li>
                            </ul>
                            <p>Good sources include fruits, vegetables, whole grains, and legumes.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Owl Carousel JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize Health Tips Carousel
        $('.health-tips-carousel').owlCarousel({
            loop: true,
            margin: 20,
            nav: true,
            dots: true,
            autoplay: true,
            autoplayTimeout: 3000,
            autoplayHoverPause: true,
            responsive: {
                0: {
                    items: 1
                },
                768: {
                    items: 2
                },
                992: {
                    items: 3
                }
            },
            navText: [
                '<i class="bi bi-chevron-left"></i>',
                '<i class="bi bi-chevron-right"></i>'
            ]
        });

        // Ingredient Search Functionality
        $('#ingredientSearch').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.ingredient-item').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });
    });
    </script>

</body>

</html>
<?php
// Close the database connection
$mysqli->close();
?>