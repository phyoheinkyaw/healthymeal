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
    payment_status TINYINT(1) DEFAULT 0 COMMENT '0: pending, 1: completed, 2: failed, 3: refunded',
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
('Morning Delivery', 'Our delivery start at 7:00 AM and end at 10:00 AM', 20000, '07:00:00', '10:00:00', 8),
('Noon Delivery', 'Our delivery start at 12:00 PM and end at 3:00 PM', 16000, '12:00:00', '15:00:00', 10),
('Evening Delivery', 'Our delivery start at 5:00 PM and end at 8:00 PM', 10000, '17:00:00', '20:00:00', 15);

-- Insert users
-- Users (1372004zinlaimon)
INSERT INTO users VALUES
(1,'admin','admin@healthymeal.com','$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq','Admin User',1,1,'2025-05-31 12:12:23',NULL,NULL,NULL,'2025-05-30 04:13:14'),
(2,'a_mon','amonpooh@gmail.com','$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq','A Mon',0,1,NULL,NULL,NULL,NULL,'2025-05-30 04:13:14'),
(3,'zin_lai','zinlai@example.com','$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq','Zin_Lai',0,1,NULL,NULL,NULL,NULL,'2025-05-30 04:13:14'),
(4,'mike_wilson','mike@example.com','$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq','Mike Wilson',0,1,NULL,NULL,NULL,NULL,'2025-05-30 04:13:14'),
(5,'sarah_brown','sarah@example.com','$2y$10$9ClmaEyPPcO47uITkn9Vh.3FzMWddrxp//VuBPA2Kx4o/4qB/1Dyq','Sarah Brown',0,1,NULL,NULL,NULL,NULL,'2025-05-30 04:13:14'),
(6,'allena345','allena345@gmail.com','$2y$10$kntwMdrZ.ief7kz16xuLFux/sJC7WcguAUvrzwUfBMbrfkwYlTp3i','Alle na',0,1,'2025-05-31 21:31:43',NULL,NULL,NULL,'2025-05-30 04:20:22');

-- Insert categories
INSERT INTO categories (name, description) VALUES
('Breakfast', 'Start your day right with our healthy breakfast options'),
('Lunch', 'Nutritious and filling lunch meals'),
('Dinner', 'Light and healthy dinner options'),
('Snacks', 'Healthy snacking options'),
('Desserts', 'Guilt-free desserts');

-- Insert ingredients
INSERT INTO ingredients VALUES 
(1,'Chicken Breast',165.00,31.00,0.00,3.60,5000,0,0,0,1,'2025-05-30 04:13:14'),
(2,'Brown Rice',111.00,2.60,23.00,0.90,1600,0,1,1,1,'2025-05-30 04:13:14'),
(3,'Broccoli',34.00,2.80,7.00,0.40,1200,0,1,1,1,'2025-05-30 04:13:14'),
(4,'Carrots',41.00,0.90,9.60,0.20,800,0,1,1,1,'2025-05-30 04:13:14'),
(5,'Olive Oil',884.00,0.00,0.00,100.00,6000,0,1,1,1,'2025-05-30 04:13:14'),
(6,'Salmon',208.00,22.00,0.00,13.00,9000,0,0,0,0,'2025-05-30 04:13:14'),
(7,'Quinoa',120.00,4.40,21.30,1.90,2400,0,1,1,1,'2025-05-30 04:13:14'),
(8,'Sweet Potato',86.00,1.60,20.10,0.10,1400,0,1,1,1,'2025-05-30 04:13:14'),
(9,'Kale',49.00,4.30,8.80,0.90,1800,0,1,1,1,'2025-05-30 04:13:14'),
(10,'Tofu',76.00,8.00,1.90,4.80,3000,0,1,1,1,'2025-05-30 04:13:14'),
(11,'Spinach',23.00,2.90,3.60,0.40,1500,0,1,1,1,'2025-05-30 04:13:14'),
(12,'Red Bell Pepper',31.00,1.00,6.00,0.30,1900,0,1,1,1,'2025-05-30 04:13:14'),
(13,'Brown Lentils',116.00,9.00,20.00,0.40,1800,0,1,1,1,'2025-05-30 04:13:14'),
(14,'Chickpeas',164.00,8.90,27.00,2.60,1600,0,1,1,1,'2025-05-30 04:13:14'),
(15,'Beef',250.00,26.00,0.00,17.00,8000,1,0,0,1,'2025-05-30 04:13:14'),
(16,'Lamb',294.00,25.00,0.00,21.00,9500,1,0,0,1,'2025-05-30 04:13:14'),
(17,'Pork',242.00,27.00,0.00,14.00,7500,1,0,0,0,'2025-05-30 04:13:14'),
(18,'Avocado',160.00,2.00,8.50,14.70,6000,0,1,1,1,'2025-05-30 04:13:14'),
(19,'Greek Yogurt',59.00,10.00,3.60,0.40,3200,0,1,0,1,'2025-05-30 04:13:14'),
(20,'Almonds',576.00,21.00,22.00,49.00,7000,0,1,1,1,'2025-05-30 04:13:14'),
(21,'RIce',60.00,70.00,34.00,67.00,2000,0,1,1,1,'2025-05-30 04:24:40'),
(22,'Cherry Tomatoe',30.00,50.00,3.00,6.00,4000,0,1,1,1,'2025-05-30 04:25:42'),
(23,'Eggplant',50.00,30.00,5.00,8.00,2000,0,1,1,1,'2025-05-30 04:26:57'),
(24,'Egg',50.00,70.00,3.00,6.00,1000,1,0,0,1,'2025-05-30 04:27:19'),
(25,'Blue Berry',40.00,50.00,5.00,9.00,6000,0,1,1,1,'2025-05-30 04:29:18'),
(26,'Banana',30.00,80.00,3.00,7.00,1000,0,1,1,1,'2025-05-30 04:30:05'),
(27,'Resberry',60.00,30.00,6.00,9.00,5000,0,1,1,1,'2025-05-30 04:30:35'),
(28,'Lentils',30.00,50.00,3.00,2.00,4000,0,1,1,1,'2025-05-30 04:33:15'),
(29,'Lemon Juice',20.00,10.00,4.00,3.00,1000,0,1,1,1,'2025-05-30 04:33:44'),
(30,'Apple',20.00,50.00,3.00,2.00,3000,0,1,1,1,'2025-05-30 04:37:35'),
(31,'Almond flour',30.00,40.00,5.00,3.00,6000,0,1,1,1,'2025-05-30 04:48:02'),
(32,'Cocoa powder',30.00,60.00,4.00,3.00,4000,0,1,1,1,'2025-05-30 04:48:28'),
(33,'Coconut sugar',30.00,20.00,3.00,6.00,3000,0,1,1,1,'2025-05-30 04:49:00');

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
INSERT INTO user_preferences VALUES 
(1,2,'none','none',1,2,2000,'2025-05-30 04:13:14','2025-05-30 04:13:14',NULL,NULL,0),
(2,3,'vegetarian','dairy',0,1,1800,'2025-05-30 04:13:14','2025-05-30 04:13:14',NULL,NULL,0),
(3,4,'halal','none',2,4,2200,'2025-05-30 04:13:14','2025-05-30 04:13:14',NULL,NULL,0),
(4,5,'vegan','nuts',1,2,1600,'2025-05-30 04:13:14','2025-05-30 04:13:14',NULL,NULL,0),
(5,6,NULL,NULL,0,1,2000,'2025-05-30 04:20:22','2025-05-30 04:20:22',NULL,NULL,0);

-- Insert user addresses
INSERT INTO user_addresses VALUES 
(1,2,'Home','123 Main St, Apt 4B','New York','10001',1,'2025-05-30 04:13:14'),
(2,2,'Work','456 Office Blvd, Suite 100','New York','10002',0,'2025-05-30 04:13:14'),
(3,3,'Home','789 Residential Ave','Los Angeles','90001',1,'2025-05-30 04:13:14'),
(4,4,'Home','101 Mountain View Rd','Denver','80201',1,'2025-05-30 04:13:14'),
(5,5,'Home','202 Seaside Dr','Miami','33101',1,'2025-05-30 04:13:14'),
(6,6,'Work','1234 Main St','Yangon','11411',1,'2025-05-30 06:09:34');

-- Insert meal kits
INSERT INTO meal_kits VALUES 
(1,'Healthy Start Breakfast Bowl','Nutritious breakfast bowl with quinoa and fruits',1,25980,270,15,1,'mk_683934b4cc7926.59920056.jpg',1,'2025-05-30 04:13:14'),
(2,'Power Lunch Box','High protein lunch with lean meat and vegetables',2,31980,398,30,1,'mk_683935f64d36e1.58604440.jpg',1,'2025-05-30 04:13:14'),
(3,'Light Dinner Delight','Low-calorie dinner option',3,29000,398,30,1,'mk_683932e4a05702.99649155.jpg',1,'2025-05-30 04:13:14'),
(4,'Energy Boost Snack Pack','Energy Boost Snack Pack with Egg, Chicken breast, olive oil, spinach, broccoli and almonds',4,17980,283,25,1,'mk_683937313c72e5.80377643.jpg',1,'2025-05-30 04:13:14'),
(5,'Guilt-free Dessert','Almond Flour Brownie',5,21980,453,20,4,'mk_68393854d98102.94742973.jpg',1,'2025-05-30 04:13:14'),
(6,'Vegan Breakfast Bowl','A delicious plant-based breakfast with quinoa, avocado, and fresh vegetables',1,24980,285,15,1,'mk_68393957a7ccb9.15669897.jpg',1,'2025-05-30 04:13:14'),
(7,'Protein-Packed Morning Start','High protein breakfast with eggs, greek yogurt, and lean meat',1,27980,186,20,1,'mk_6839337d7a00a1.56870446.jpg',1,'2025-05-30 04:13:14'),
(8,'Vegetarian Breakfast Platter','Vegetarian breakfast with tofu scramble, sweet potatoes, and vegetables',1,25000,214,25,1,'mk_683931d2960474.03489380.jpg',1,'2025-05-30 04:13:14'),
(9,'Halal Breakfast Delight','Halal-friendly breakfast with chicken, brown rice, and vegetables',1,26980,258,20,1,'mk_6839316c606ba7.51516677.jpg',1,'2025-05-30 04:13:14'),
(10,'Vegan Lunch Bowl','Plant-based lunch with lentils, chickpeas, and fresh vegetables',2,29980,310,30,1,'mk_683935717cf658.93193051.jpg',1,'2025-05-30 04:13:14'),
(11,'High-Protein Lunch Box','Protein-rich lunch with beef, quinoa, and roasted vegetables',2,33980,620,35,1,NULL,1,'2025-05-30 04:13:14'),
(12,'Vegetarian Lunch Delight','Vegetarian lunch with tofu, brown rice, and steamed vegetables',2,30980,590,25,1,NULL,1,'2025-05-30 04:13:14'),
(13,'Halal Lunch Special','Halal-friendly lunch with lamb, couscous, and mixed vegetables',2,32980,610,30,1,NULL,1,'2025-05-30 04:13:14'),
(14,'Vegan Dinner Plate','Light vegan dinner with tofu, quinoa, and steamed vegetables',3,27980,380,25,1,NULL,1,'2025-05-30 04:13:14'),
(15,'Protein Dinner Box','Protein-rich dinner with chicken breast, sweet potato, and broccoli',3,31980,420,30,1,NULL,1,'2025-05-30 04:13:14'),
(16,'Vegetarian Dinner Special','Vegetarian dinner with plant protein, brown rice, and seasonal vegetables',3,28980,390,25,1,NULL,1,'2025-05-30 04:13:14'),
(17,'Halal Dinner Delight','Halal-friendly dinner with beef, vegetables, and light sauce',3,30980,410,30,1,NULL,1,'2025-05-30 04:13:14'),
(18,'Vegan Snack Pack','Plant-based snacks with nuts, dried fruits, and vegetable chips',4,16980,190,10,1,NULL,1,'2025-05-30 04:13:14'),
(19,'Protein Snack Box','Protein-rich snacks with Greek yogurt, nuts, and lean meat jerky',4,18980,210,5,1,NULL,1,'2025-05-30 04:13:14'),
(20,'Vegetarian Snack Delight','Vegetarian snacks with cheese, crackers, and fresh fruits',4,17980,200,5,1,NULL,1,'2025-05-30 04:13:14'),
(21,'Halal Snack Special','Halal-friendly snacks with halal meat, dates, and nuts',4,17980,205,5,1,NULL,1,'2025-05-30 04:13:14'),
(22,'Vegan Sweet Treat','Plant-based dessert with fruit compote and nut toppings',5,20980,280,15,1,NULL,1,'2025-05-30 04:13:14'),
(23,'Protein Dessert Box','Protein-enriched dessert with Greek yogurt and berries',5,22980,320,10,1,NULL,1,'2025-05-30 04:13:14'),
(24,'Vegetarian Dessert Delight','Vegetarian dessert with honey, yogurt, and fresh fruits',5,21980,290,15,1,NULL,1,'2025-05-30 04:13:14'),
(25,'Halal Dessert Special','Halal-friendly dessert with dates, nuts, and natural sweeteners',5,21980,310,10,1,NULL,1,'2025-05-30 04:13:14');

-- Insert meal kit ingredients
INSERT INTO meal_kit_ingredients VALUES 
(1,20,40),
(1,25,40),
(1,26,20),
(1,27,30),
(2,1,200),
(2,3,150),
(2,22,20),
(2,24,10),
(2,30,30),
(3,1,100),
(3,3,40),
(3,4,180),
(3,5,10),
(3,11,20),
(3,12,10),
(3,14,30),
(4,1,100),
(4,5,10),
(4,11,40),
(4,19,20),
(4,22,30),
(5,5,20),
(5,20,40),
(5,24,20),
(5,31,50),
(5,32,50),(5,33,20),(6,5,5),(6,7,100),(6,11,50),(6,12,50),(6,18,50),(6,22,30),(6,24,10),(7,3,50),(7,18,50),(7,19,100),(7,22,50),(7,23,20),(7,24,10),(8,8,80),(8,10,100),(8,11,40),(8,12,40),(8,18,30),(9,1,80),(9,2,80),(9,3,50),(9,4,50),(10,11,50),(10,12,20),(10,13,100),(10,14,100),(10,19,20),(11,3,60),(11,4,60),(11,7,80),(11,15,120),(12,2,100),(12,3,70),(12,10,120),(12,11,50),(13,2,100),(13,4,60),(13,12,60),(13,16,120),(14,7,70),(14,10,100),(14,11,60),(14,18,30),(15,1,120),(15,3,80),(15,8,100),(16,2,80),(16,10,100),(16,11,60),(16,12,60),(17,3,70),(17,4,70),(17,5,10),(17,15,100),(18,14,40),(18,18,20),(18,20,30),(19,1,20),(19,19,60),(19,20,30),(20,18,20),(20,19,60),(20,20,30),(21,1,30),(21,5,5),(21,20,40),(22,5,5),(22,18,50),(22,20,30),(23,19,100),(23,20,30),(24,18,30),(24,19,80),(24,20,30),(25,5,10),(25,20,60);

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