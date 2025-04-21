DROP DATABASE IF EXISTS healthy_meal_kit;

-- Create database
CREATE DATABASE IF NOT EXISTS healthy_meal_kit;
USE healthy_meal_kit;

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Preferences table
CREATE TABLE user_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    dietary_restrictions VARCHAR(50) DEFAULT NULL,
    allergies TEXT,
    cooking_experience ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    household_size INT DEFAULT 1,
    calorie_goal INT DEFAULT 2000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    daily_calorie_goal INT,
    weekly_calorie_goal INT,
    dietary_preference ENUM('None', 'Vegan', 'Vegetarian', 'Halal'),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Categories table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT
);

-- Ingredients table
CREATE TABLE ingredients (
    ingredient_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    calories_per_100g DECIMAL(10,2) NOT NULL,
    protein_per_100g DECIMAL(10,2) NOT NULL,
    carbs_per_100g DECIMAL(10,2) NOT NULL,
    fat_per_100g DECIMAL(10,2) NOT NULL,
    price_per_100g DECIMAL(10,2) NOT NULL,
    is_meat BOOLEAN DEFAULT FALSE,
    is_vegetarian BOOLEAN DEFAULT FALSE,
    is_vegan BOOLEAN DEFAULT FALSE,
    is_halal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Meal Kits table
CREATE TABLE meal_kits (
    meal_kit_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    preparation_price DECIMAL(10,2) NOT NULL,
    base_calories INT NOT NULL COMMENT 'Calculated from sum of ingredients',
    cooking_time INT,
    servings INT,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Meal Kit Ingredients table
CREATE TABLE meal_kit_ingredients (
    meal_kit_id INT,
    ingredient_id INT,
    default_quantity INT,
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id),
    PRIMARY KEY (meal_kit_id, ingredient_id)
);

-- Order Status table
CREATE TABLE order_status (
    status_id INT PRIMARY KEY AUTO_INCREMENT,
    status_name VARCHAR(50) NOT NULL
);

-- Insert default statuses
INSERT INTO order_status (status_name) VALUES
('pending'),
('confirmed'),
('delivered'),
('cancelled');

-- Orders table
CREATE TABLE orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    meal_kit_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delivery_address TEXT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    delivery_notes TEXT,
    payment_method VARCHAR(50) NOT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id),
    FOREIGN KEY (status_id) REFERENCES order_status(status_id)
);

-- Order Items Table
CREATE TABLE order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    meal_kit_id INT NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    customization_notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id)
);

-- Order Item Ingredients Table: stores per-ingredient customization for each order item
CREATE TABLE order_item_ingredients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_item_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    custom_grams DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);

-- Blog Posts table
CREATE TABLE blog_posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    author_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(user_id)
);

-- Comments table
CREATE TABLE comments (
    comment_id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT,
    user_id INT,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(post_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Health Tips table
CREATE TABLE health_tips (
    tip_id INT PRIMARY KEY AUTO_INCREMENT,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Remember Tokens table
CREATE TABLE remember_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_token (token)
);

-- Cart items table
CREATE TABLE cart_items (
    cart_item_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    meal_kit_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    customization_notes TEXT,
    single_meal_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id) ON DELETE CASCADE
);

-- Cart item ingredients table to store customized ingredient quantities
CREATE TABLE cart_item_ingredients (
    cart_item_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (cart_item_id, ingredient_id),
    FOREIGN KEY (cart_item_id) REFERENCES cart_items(cart_item_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE CASCADE
);

-- Insert sample data
-- Users (1372004zinlaimon)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@healthymeal.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Admin User', 'admin'),
('john_doe', 'john@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'John Doe', 'user'),
('jane_smith', 'jane@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Jane Smith', 'user'),
('mike_wilson', 'mike@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Mike Wilson', 'user'),
('sarah_brown', 'sarah@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Sarah Brown', 'user');

-- User Preferences
INSERT INTO user_preferences (user_id, dietary_restrictions, allergies, cooking_experience, household_size, calorie_goal) VALUES
(2, 'none', 'none', 'intermediate', 2, 2000),
(3, 'vegetarian', 'dairy', 'beginner', 1, 1800),
(4, 'halal', 'none', 'advanced', 4, 2200),
(5, 'vegan', 'nuts', 'intermediate', 2, 1600);

-- Categories
INSERT INTO categories (name, description) VALUES
('Breakfast', 'Start your day right with our healthy breakfast options'),
('Lunch', 'Nutritious and filling lunch meals'),
('Dinner', 'Light and healthy dinner options'),
('Snacks', 'Healthy snacking options'),
('Desserts', 'Guilt-free desserts');

-- Ingredients
INSERT INTO ingredients (name, calories_per_100g, protein_per_100g, carbs_per_100g, fat_per_100g, price_per_100g, is_vegetarian, is_vegan, is_halal) VALUES
('Chicken Breast', 165, 31, 0, 3.6, 2.50, FALSE, FALSE, TRUE),
('Brown Rice', 111, 2.6, 23, 0.9, 0.80, TRUE, TRUE, TRUE),
('Broccoli', 34, 2.8, 7, 0.4, 0.60, TRUE, TRUE, TRUE),
('Carrots', 41, 0.9, 9.6, 0.2, 0.40, TRUE, TRUE, TRUE),
('Olive Oil', 884, 0, 0, 100, 3.00, TRUE, TRUE, TRUE),
('Salmon', 208, 22, 0, 13, 4.50, FALSE, FALSE, FALSE),
('Quinoa', 120, 4.4, 21.3, 1.9, 1.20, TRUE, TRUE, TRUE),
('Sweet Potato', 86, 1.6, 20.1, 0.1, 0.70, TRUE, TRUE, TRUE),
('Kale', 49, 4.3, 8.8, 0.9, 0.90, TRUE, TRUE, TRUE),
('Tofu', 76, 8, 1.9, 4.8, 1.50, TRUE, TRUE, TRUE);

-- Meal Kits
INSERT INTO meal_kits (name, description, category_id, preparation_price, base_calories, image_url) VALUES
('Healthy Start Breakfast Bowl', 'Nutritious breakfast bowl with quinoa and fruits', 1, 12.99, 450, ''),
('Power Lunch Box', 'High protein lunch with lean meat and vegetables', 2, 15.99, 600, ''),
('Light Dinner Delight', 'Low-calorie dinner option', 3, 14.99, 400, ''),
('Energy Boost Snack Pack', 'Healthy snacking option', 4, 8.99, 200, ''),
('Guilt-free Dessert Box', 'Healthy dessert options', 5, 10.99, 300, '');

-- Meal Kit Ingredients
INSERT INTO meal_kit_ingredients (meal_kit_id, ingredient_id, default_quantity) VALUES
(1, 2, 100),
(1, 3, 150),
(2, 1, 200),
(2, 3, 150),
(3, 4, 180);

-- Blog Posts
INSERT INTO blog_posts (title, content, author_id) VALUES
('Benefits of Meal Planning', 'Learn how meal planning can help you achieve your health goals...', 1),
('Understanding Macronutrients', 'A comprehensive guide to proteins, carbs, and fats...', 1),
('Healthy Cooking Tips', 'Simple tips to make your cooking healthier...', 1),
('Meal Prep 101', 'Getting started with meal preparation...', 1),
('Nutrition Myths Debunked', 'Common nutrition myths and the truth behind them...', 1);

-- Comments
INSERT INTO comments (post_id, user_id, content) VALUES
(1, 2, 'Great article! Very helpful information.'),
(1, 3, 'This helped me start my meal planning journey.'),
(2, 4, 'Finally understanding macros better!'),
(3, 5, 'These tips are game-changers!'),
(4, 2, 'Perfect guide for beginners.');

-- Health Tips
INSERT INTO health_tips (content) VALUES
('Drink at least 8 glasses of water daily for optimal hydration.'),
('Include a variety of colorful vegetables in your meals for better nutrition.'),
('Regular exercise combined with healthy eating leads to better results.'),
('Get adequate sleep to support your health and fitness goals.'),
('Practice mindful eating for better digestion and portion control.');

-- Insert Sample Orders (assuming user_id 2 exists)
INSERT INTO orders (user_id, meal_kit_id, quantity, total_price, status_id, created_at, delivery_address, contact_number, delivery_notes, payment_method, delivery_fee) VALUES
(2, 1, 1, 24.99, 4, '2024-02-01 10:00:00', '123 Main St, City, State 12345', '555-0123', 'Please leave at front door', 'Credit Card', 5.00),
(2, 2, 1, 19.99, 2, '2024-02-15 14:30:00', '123 Main St, City, State 12345', '555-0123', NULL, 'PayPal', 5.00),
(2, 3, 1, 22.99, 2, '2024-02-28 09:15:00', '123 Main St, City, State 12345', '555-0123', 'Ring doorbell', 'Credit Card', 5.00),
(2, 2, 1, 19.99, 1, '2024-03-01 16:45:00', '123 Main St, City, State 12345', '555-0123', NULL, 'Credit Card', 5.00),
(2, 1, 1, 24.99, 3, '2024-02-10 11:20:00', '123 Main St, City, State 12345', '555-0123', 'Cancelled due to out of stock', 'Credit Card', 5.00);

-- Insert Sample Order Items (assuming meal_kit_id 1-3 exist)
INSERT INTO order_items (order_id, meal_kit_id, quantity, price_per_unit, customization_notes) VALUES
-- Order 1 items
(1, 1, 2, 24.99, 'Extra spicy, no cilantro'),
(1, 2, 1, 19.99, NULL),
(1, 3, 1, 22.99, 'Gluten-free option'),

-- Order 2 items
(2, 2, 2, 19.99, 'Regular spice level'),
(2, 3, 1, 22.99, NULL),

-- Order 3 items
(3, 1, 1, 24.99, 'Vegetarian option'),
(3, 3, 2, 22.99, 'Extra vegetables'),

-- Order 4 items
(4, 2, 3, 19.99, NULL),
(4, 3, 1, 22.99, 'No nuts'),

-- Order 5 items
(5, 1, 1, 24.99, NULL),
(5, 2, 1, 19.99, 'Low sodium');

-- Insert Sample Order Item Ingredients
INSERT INTO order_item_ingredients (order_item_id, ingredient_id, custom_grams) VALUES
    (1, 1, 150.00), -- Chicken 150g for order_item_id 1
    (1, 2, 100.00), -- Rice 100g for order_item_id 1
    (1, 3, 50.00),  -- Broccoli 50g for order_item_id 1
    (2, 3, 120.00); -- Broccoli 120g for order_item_id 2

-- Update sample data for meal kits
UPDATE meal_kits SET 
    preparation_price = 12.99,
    base_calories = 450
WHERE meal_kit_id = 1;

UPDATE meal_kits SET 
    preparation_price = 15.99,
    base_calories = 600
WHERE meal_kit_id = 2;

UPDATE meal_kits SET 
    preparation_price = 14.99,
    base_calories = 400
WHERE meal_kit_id = 3;

UPDATE meal_kits SET 
    preparation_price = 8.99,
    base_calories = 200
WHERE meal_kit_id = 4;

UPDATE meal_kits SET 
    preparation_price = 10.99,
    base_calories = 300
WHERE meal_kit_id = 5;