DROP DATABASE IF EXISTS healthy_meal_kit;

-- Create database
CREATE DATABASE IF NOT EXISTS healthy_meal_kit;
USE healthy_meal_kit;

-- =============================================
-- TABLE CREATION - Core tables with no foreign keys
-- =============================================

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role TINYINT(1) DEFAULT 0 COMMENT '0: user, 1: admin',
    is_active TINYINT(1) DEFAULT 1 COMMENT '0: inactive, 1: active',
    last_login_at DATETIME DEFAULT NULL,
    inactivity_reason VARCHAR(255) DEFAULT NULL,
    deactivated_at DATETIME DEFAULT NULL,
    reactivated_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ingredients table
CREATE TABLE ingredients (
    ingredient_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    calories_per_100g DECIMAL(10,2) NOT NULL,
    protein_per_100g DECIMAL(10,2) NOT NULL,
    carbs_per_100g DECIMAL(10,2) NOT NULL,
    fat_per_100g DECIMAL(10,2) NOT NULL,
    price_per_100g INT NOT NULL,
    is_meat TINYINT(1) DEFAULT 0 COMMENT '0: false, 1: true',
    is_vegetarian TINYINT(1) DEFAULT 0 COMMENT '0: false, 1: true',
    is_vegan TINYINT(1) DEFAULT 0 COMMENT '0: false, 1: true',
    is_halal TINYINT(1) DEFAULT 0 COMMENT '0: false, 1: true',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Order Status table
CREATE TABLE order_status (
    status_id INT PRIMARY KEY AUTO_INCREMENT,
    status_name VARCHAR(50) NOT NULL
);

-- Payment Settings table
CREATE TABLE payment_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_method VARCHAR(50) NOT NULL, -- e.g., 'KBZPay'
    qr_code VARCHAR(255) NULL,           -- Path to QR code image
    account_phone VARCHAR(50) NULL,      -- Phone number for payment
    description TEXT NULL,               -- Description of the payment method
    bank_info TEXT NULL,                 -- Bank information as an alternative to QR code
    icon_class VARCHAR(50) DEFAULT 'bi bi-credit-card', -- Icon class for UI display
    is_active TINYINT(1) DEFAULT 1 COMMENT '0: inactive, 1: active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Delivery Options table
CREATE TABLE delivery_options (
    delivery_option_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    fee INT NOT NULL,
    time_slot TIME NOT NULL,
    cutoff_time TIME NOT NULL COMMENT 'Order cutoff time for this delivery slot',
    max_orders_per_slot INT NOT NULL DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1 COMMENT '0: inactive, 1: active'
);

-- Health Tips table
CREATE TABLE health_tips (
    tip_id INT PRIMARY KEY AUTO_INCREMENT,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- TABLE CREATION - Tables with foreign keys
-- =============================================

-- User Preferences table
CREATE TABLE user_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    dietary_restrictions VARCHAR(50) DEFAULT NULL,
    allergies TEXT,
    cooking_experience TINYINT(1) DEFAULT 0 COMMENT '0: beginner, 1: intermediate, 2: advanced',
    household_size INT DEFAULT 1,
    calorie_goal INT DEFAULT 2000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    daily_calorie_goal INT,
    weekly_calorie_goal INT,
    dietary_preference TINYINT(1) DEFAULT 0 COMMENT '0: None, 1: Vegan, 2: Vegetarian, 3: Halal',
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- User Addresses table
CREATE TABLE user_addresses (
    address_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    address_name VARCHAR(50) NOT NULL,
    full_address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    is_default TINYINT(1) DEFAULT 0 COMMENT '0: not default, 1: default',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
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

-- Meal Kits table
CREATE TABLE meal_kits (
    meal_kit_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    preparation_price INT NOT NULL,
    base_calories INT NOT NULL COMMENT 'Calculated from sum of ingredients',
    cooking_time INT,
    servings INT,
    image_url VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1 COMMENT '0: inactive, 1: active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- Orders table
CREATE TABLE orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    status_id INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    delivery_address TEXT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    account_phone VARCHAR(50) NULL COMMENT 'Phone number for refund payments',
    delivery_notes TEXT,
    payment_method_id INT NOT NULL,
    payment_reference VARCHAR(50) NULL COMMENT 'Unique reference code for payment identification',
    is_paid TINYINT(1) DEFAULT 0 COMMENT '0: not paid, 1: paid',
    delivery_fee INT NOT NULL DEFAULT 0,
    delivery_option_id INT NULL,
    expected_delivery_date DATE NULL,
    preferred_delivery_time TIME NULL,
    subtotal INT NOT NULL DEFAULT 0,
    tax INT NOT NULL DEFAULT 0,
    total_amount INT NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (status_id) REFERENCES order_status(status_id),
    FOREIGN KEY (delivery_option_id) REFERENCES delivery_options(delivery_option_id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_settings(id)
);

-- Blog Posts table
CREATE TABLE blog_posts (
    post_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    author_id INT,
    image_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(user_id)
);

-- Payment History table
CREATE TABLE payment_history (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    amount INT NOT NULL,
    payment_method_id INT NOT NULL,
    transaction_id VARCHAR(100) NULL COMMENT 'External payment provider transaction ID',
    payment_reference VARCHAR(50) NULL COMMENT 'Matches order payment_reference',
    payment_status TINYINT(1) DEFAULT 0 COMMENT '0: pending, 1: completed, 2: failed, 3: refunded',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_settings(id)
);

-- =============================================
-- TABLE CREATION - Tables with multiple foreign keys
-- =============================================

-- Meal Kit Ingredients table
CREATE TABLE meal_kit_ingredients (
    meal_kit_id INT,
    ingredient_id INT,
    default_quantity INT,
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id),
    PRIMARY KEY (meal_kit_id, ingredient_id)
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

-- Order Items Table
CREATE TABLE order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    meal_kit_id INT NOT NULL,
    quantity INT NOT NULL,
    price_per_unit INT NOT NULL,
    customization_notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id)
);

-- Payment Verifications table
CREATE TABLE payment_verifications (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_id INT NULL,
    transaction_id VARCHAR(100) NULL COMMENT 'Transaction ID from customer',
    amount_verified INT NOT NULL,
    payment_status TINYINT(1) DEFAULT 0 COMMENT '0: pending, 1: completed, 2: failed, 3: refunded, 4: partial',
    verification_notes TEXT,
    verified_by_id INT NULL,
    transfer_slip VARCHAR(255) NULL COMMENT 'Path to uploaded payment proof image',
    payment_verified TINYINT(1) DEFAULT 0 COMMENT '0: not verified, 1: verified',
    payment_verified_at DATETIME NULL,
    additional_proof_requested TINYINT(1) DEFAULT 0 COMMENT '0: no, 1: yes',
    verification_attempt INT DEFAULT 1 COMMENT 'Which attempt at verification this is',
    resubmission_status TINYINT(1) DEFAULT 0 COMMENT '0: original, 1: resubmitted, 2: pending resubmission',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payment_history(payment_id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by_id) REFERENCES users(user_id)
);

-- Order Notifications table
CREATE TABLE order_notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    note TEXT NULL COMMENT 'Additional details or admin notes',
    is_read TINYINT(1) DEFAULT 0 COMMENT '0: unread, 1: read',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Payment Verification Logs table
CREATE TABLE payment_verification_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    verification_id INT NOT NULL,
    order_id INT NOT NULL,
    status_changed_from TINYINT(1),
    status_changed_to TINYINT(1),
    amount INT NOT NULL,
    admin_notes TEXT,
    verified_by_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (verification_id) REFERENCES payment_verifications(verification_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (verified_by_id) REFERENCES users(user_id)
);

-- Cart items table
CREATE TABLE cart_items (
    cart_item_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    meal_kit_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    customization_notes TEXT,
    single_meal_price INT NOT NULL,
    total_price INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id) ON DELETE CASCADE
);

-- Order Item Ingredients Table
CREATE TABLE order_item_ingredients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_item_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    custom_grams DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_item_id) REFERENCES order_items(order_item_id),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);

-- Cart item ingredients table
CREATE TABLE cart_item_ingredients (
    cart_item_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    price INT NOT NULL,
    PRIMARY KEY (cart_item_id, ingredient_id),
    FOREIGN KEY (cart_item_id) REFERENCES cart_items(cart_item_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id) ON DELETE CASCADE
);

-- User Favorites table
CREATE TABLE user_favorites (
    favorite_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    meal_kit_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (meal_kit_id) REFERENCES meal_kits(meal_kit_id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, meal_kit_id)
);

-- =============================================
-- CREATE INDEXES
-- =============================================

-- Create indexes for payment_verifications table
CREATE INDEX idx_payment_verifications_order_id ON payment_verifications(order_id);
CREATE INDEX idx_payment_verifications_payment_id ON payment_verifications(payment_id);

-- =============================================
-- INSERT DATA - Core tables data
-- =============================================

-- Insert default status values
INSERT INTO order_status (status_name) VALUES
('pending'),
('confirmed'),
('preparing'),
('out_for_delivery'),
('delivered'),
('failed_delivery'),
('cancelled');

-- Insert default payment methods
INSERT INTO payment_settings (payment_method, qr_code, account_phone, description, bank_info, icon_class, is_active)
VALUES 
('KBZPay', NULL, '09123456789', 'Pay securely with KBZPay mobile payment service. Scan the QR code or send directly to our account.', NULL, 'bi bi-phone-fill', 1),
('Cash on Delivery', NULL, NULL, 'Pay with cash when your order is delivered to your address. No advance payment required.', NULL, 'bi bi-cash-coin', 1);

-- Insert default delivery options
INSERT INTO delivery_options (name, description, fee, time_slot, cutoff_time, max_orders_per_slot) VALUES
('Morning Delivery', 'Morning delivery between 8:00 AM - 10:00 AM', 20000, '09:00:00', '18:00:00', 8),
('Noon Delivery', 'Noon delivery between 11:30 AM - 1:30 PM', 16000, '12:00:00', '20:00:00', 10),
('Evening Delivery', 'Evening delivery between 4:00 PM - 6:00 PM', 10000, '17:00:00', '12:00:00', 15);

-- Insert users
-- Users (1372004zinlaimon)
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@healthymeal.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Admin User', 1),
('john_doe', 'john@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'John Doe', 0),
('jane_smith', 'jane@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Jane Smith', 0),
('mike_wilson', 'mike@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Mike Wilson', 0),
('sarah_brown', 'sarah@example.com', '$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq', 'Sarah Brown', 0);

-- Insert categories
INSERT INTO categories (name, description) VALUES
('Breakfast', 'Start your day right with our healthy breakfast options'),
('Lunch', 'Nutritious and filling lunch meals'),
('Dinner', 'Light and healthy dinner options'),
('Snacks', 'Healthy snacking options'),
('Desserts', 'Guilt-free desserts');

-- Insert ingredients
INSERT INTO ingredients (name, calories_per_100g, protein_per_100g, carbs_per_100g, fat_per_100g, price_per_100g, is_vegetarian, is_vegan, is_halal) VALUES
('Chicken Breast', 165, 31, 0, 3.6, 5000, 0, 0, 1),
('Brown Rice', 111, 2.6, 23, 0.9, 1600, 1, 1, 1),
('Broccoli', 34, 2.8, 7, 0.4, 1200, 1, 1, 1),
('Carrots', 41, 0.9, 9.6, 0.2, 800, 1, 1, 1),
('Olive Oil', 884, 0, 0, 100, 6000, 1, 1, 1),
('Salmon', 208, 22, 0, 13, 9000, 0, 0, 0),
('Quinoa', 120, 4.4, 21.3, 1.9, 2400, 1, 1, 1),
('Sweet Potato', 86, 1.6, 20.1, 0.1, 1400, 1, 1, 1),
('Kale', 49, 4.3, 8.8, 0.9, 1800, 1, 1, 1),
('Tofu', 76, 8, 1.9, 4.8, 3000, 1, 1, 1);

-- Insert health tips
INSERT INTO health_tips (content) VALUES
('Drink at least 8 glasses of water daily for optimal hydration.'),
('Include a variety of colorful vegetables in your meals for better nutrition.'),
('Regular exercise combined with healthy eating leads to better results.'),
('Get adequate sleep to support your health and fitness goals.'),
('Practice mindful eating for better digestion and portion control.');

-- =============================================
-- INSERT DATA - Foreign key dependent tables
-- =============================================

-- Insert user preferences
INSERT INTO user_preferences (user_id, dietary_restrictions, allergies, cooking_experience, household_size, calorie_goal) VALUES
(2, 'none', 'none', 1, 2, 2000),
(3, 'vegetarian', 'dairy', 0, 1, 1800),
(4, 'halal', 'none', 2, 4, 2200),
(5, 'vegan', 'nuts', 1, 2, 1600);

-- Insert user addresses
INSERT INTO user_addresses (user_id, address_name, full_address, city, postal_code, is_default) VALUES
(2, 'Home', '123 Main St, Apt 4B', 'New York', '10001', 1),
(2, 'Work', '456 Office Blvd, Suite 100', 'New York', '10002', 0),
(3, 'Home', '789 Residential Ave', 'Los Angeles', '90001', 1),
(4, 'Home', '101 Mountain View Rd', 'Denver', '80201', 1),
(5, 'Home', '202 Seaside Dr', 'Miami', '33101', 1);

-- Insert meal kits
INSERT INTO meal_kits (name, description, category_id, preparation_price, base_calories, image_url) VALUES
('Healthy Start Breakfast Bowl', 'Nutritious breakfast bowl with quinoa and fruits', 1, 25980, 450, ''),
('Power Lunch Box', 'High protein lunch with lean meat and vegetables', 2, 31980, 600, ''),
('Light Dinner Delight', 'Low-calorie dinner option', 3, 29980, 400, ''),
('Energy Boost Snack Pack', 'Healthy snacking option', 4, 17980, 200, ''),
('Guilt-free Dessert Box', 'Healthy dessert options', 5, 21980, 300, '');

-- Insert meal kit ingredients
INSERT INTO meal_kit_ingredients (meal_kit_id, ingredient_id, default_quantity) VALUES
(1, 2, 100),
(1, 3, 150),
(2, 1, 200),
(2, 3, 150),
(3, 4, 180);

-- Insert blog posts
INSERT INTO blog_posts (title, content, author_id, image_url) VALUES
('Benefits of Meal Planning', 'Learn how meal planning can help you achieve your health goals...', 1, ''),
('Understanding Macronutrients', 'A comprehensive guide to proteins, carbs, and fats...', 1, ''),
('Healthy Cooking Tips', 'Simple tips to make your cooking healthier...', 1, ''),
('Meal Prep 101', 'Getting started with meal preparation...', 1, ''),
('Nutrition Myths Debunked', 'Common nutrition myths and the truth behind them...', 1, '');

-- Insert blog comments
INSERT INTO comments (post_id, user_id, content) VALUES
(1, 2, 'Great article! Very helpful information.'),
(1, 3, 'This helped me start my meal planning journey.'),
(2, 4, 'Finally understanding macros better!'),
(3, 5, 'These tips are game-changers!'),
(4, 2, 'Perfect guide for beginners.');

-- =============================================
-- INSERT DATA - Complex relations
-- =============================================

-- Insert sample orders
INSERT INTO orders (user_id, status_id, created_at, delivery_address, contact_number, customer_phone, account_phone, delivery_notes, payment_method_id, payment_reference, is_paid, delivery_fee, delivery_option_id, subtotal, tax, total_amount) VALUES
(2, 4, '2024-02-01 10:00:00', '123 Main St, City, State 12345', '555-0123', '555-0123', '09123456789', 'Please leave at front door', 1, 'PAY-REF-001', 1, 10000, 1, 135940, 6800, 152740),
(2, 2, '2024-02-15 14:30:00', '123 Main St, City, State 12345', '555-0123', '555-0123', '09123456789', NULL, 1, 'PAY-REF-002', 1, 10000, 1, 125940, 6300, 142240),
(2, 2, '2024-02-28 09:15:00', '123 Main St, City, State 12345', '555-0123', '555-0123', '09123456789', 'Ring doorbell', 1, 'PAY-REF-003', 1, 10000, 1, 141940, 7100, 159040),
(2, 1, '2024-03-01 16:45:00', '123 Main St, City, State 12345', '555-0123', '555-0123', '09123456789', NULL, 1, 'PAY-REF-004', 0, 10000, 1, 165920, 8300, 184220),
(2, 3, '2024-02-10 11:20:00', '123 Main St, City, State 12345', '555-0123', '555-0123', '09123456789', 'Cancelled due to out of stock', 1, 'PAY-REF-005', 1, 10000, 1, 89960, 4500, 104460);

-- Insert order items
INSERT INTO order_items (order_id, meal_kit_id, quantity, price_per_unit, customization_notes) VALUES
-- Order 1 items
(1, 1, 2, 49980, 'Extra spicy, no cilantro'),
(1, 2, 1, 39980, NULL),
(1, 3, 1, 45980, 'Gluten-free option'),

-- Order 2 items
(2, 2, 2, 39980, 'Regular spice level'),
(2, 3, 1, 45980, NULL),

-- Order 3 items
(3, 1, 1, 49980, 'Vegetarian option'),
(3, 3, 2, 45980, 'Extra vegetables'),

-- Order 4 items
(4, 2, 3, 39980, NULL),
(4, 3, 1, 45980, 'No nuts'),

-- Order 5 items
(5, 1, 1, 49980, NULL),
(5, 2, 1, 39980, 'Low sodium');

-- Insert payment history
INSERT INTO payment_history (order_id, amount, payment_method_id, transaction_id, payment_reference, payment_status) VALUES
(1, 159540, 1, 'TXN123456789', 'PAY-REF-001', 1),
(2, 148540, 1, 'PP987654321', 'PAY-REF-002', 1),
(3, 166140, 1, 'TXN567891234', 'PAY-REF-003', 1),
(4, 92110, 1, 'PARTIAL-PAY', 'PAY-REF-004', 0), -- Half payment
(5, 108960, 1, 'TXN456789123', 'PAY-REF-005', 3); -- Refunded

-- Insert order item ingredients
INSERT INTO order_item_ingredients (order_item_id, ingredient_id, custom_grams) VALUES
    (1, 1, 150.00), -- Chicken 150g for order_item_id 1
    (1, 2, 100.00), -- Rice 100g for order_item_id 1
    (1, 3, 50.00),  -- Broccoli 50g for order_item_id 1
    (2, 3, 120.00); -- Broccoli 120g for order_item_id 2

-- Insert payment verifications
INSERT INTO payment_verifications (order_id, payment_id, transaction_id, amount_verified, payment_status, verification_notes, verified_by_id, transfer_slip, payment_verified, payment_verified_at, verification_attempt, resubmission_status) VALUES
(1, 1, 'TXN123456789', 159540, 1, 'Payment slip matches transaction details', 1, 'slip1.jpg', 1, '2024-02-01 11:30:00', 1, 0),
(2, 2, 'PP987654321', 148540, 1, 'Verified through PayPal API', 1, 'slip2.jpg', 1, '2024-02-15 15:45:00', 1, 0),
(3, 3, 'TXN567891234', 166140, 1, 'Transaction confirmed in bank statement', 1, 'slip3.jpg', 1, '2024-02-28 10:20:00', 1, 0),
(4, 4, 'PARTIAL-PAY', 92110, 4, 'Partial payment verified, waiting for remaining amount', 1, 'slip4.jpg', 0, NULL, 1, 0),
(4, NULL, NULL, 0, 0, 'Awaiting payment for remaining balance', 1, NULL, 0, NULL, 2, 2),
(5, 5, 'TXN456789123', 108960, 3, 'Refunded due to out of stock', 1, 'slip5.jpg', 1, '2024-02-10 13:15:00', 1, 0);

-- Insert payment verification logs
INSERT INTO payment_verification_logs (verification_id, order_id, status_changed_from, status_changed_to, amount, admin_notes, verified_by_id) VALUES
(1, 1, 0, 1, 159540, 'Payment verified successfully', 1),
(2, 2, 0, 1, 148540, 'Payment verified successfully', 1),
(3, 3, 0, 1, 166140, 'Payment verified successfully', 1),
(4, 4, 0, 4, 92110, 'Partial payment received', 1),
(6, 5, 0, 3, 108960, 'Payment refunded to customer', 1);

-- Insert order notifications
INSERT INTO order_notifications (order_id, user_id, message, note, is_read) VALUES
(1, 2, 'Your order has been cancelled as requested.', 'Payment not verified', 1),
(2, 2, 'Your order has been confirmed and is being prepared.', NULL, 1),
(3, 2, 'Your order has been confirmed and is being prepared.', NULL, 0),
(4, 2, 'Your order has been received and is pending payment confirmation.', NULL, 0),
(5, 2, 'Your order has been delivered. Enjoy your meal!', NULL, 1);