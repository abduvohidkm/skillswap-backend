-- =====================================================
-- SKILL SWAP ACADEMY - DATABASE SCHEMA
-- Complete schema for courses management with categories
-- =====================================================

-- 1. CREATE CATEGORIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (name, slug) VALUES
('Programming', 'programming'),
('Design', 'design'),
('Business', 'business'),
('Marketing', 'marketing')
ON DUPLICATE KEY UPDATE name=name;

-- 2. CREATE/UPDATE COURSES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS courses (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    is_free BOOLEAN DEFAULT 1,
    image_url TEXT NOT NULL,
    telegram_link VARCHAR(255) NOT NULL,
    instructor_name VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- 3. ADD CATEGORY_ID TO EXISTING COURSES (if upgrading)
-- =====================================================
-- If you have existing courses table, run this:
-- ALTER TABLE courses ADD COLUMN category_id INT NOT NULL DEFAULT 1 AFTER description;
-- ALTER TABLE courses ADD COLUMN is_free BOOLEAN DEFAULT 1 AFTER price;
-- ALTER TABLE courses ADD COLUMN instructor_name VARCHAR(100) AFTER telegram_link;
-- ALTER TABLE courses ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT;

-- 4. CREATE COURSE_VIEWS TABLE (optional - for analytics)
-- =====================================================
CREATE TABLE IF NOT EXISTS course_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36),
    ip_address VARCHAR(45),
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- 5. CREATE COURSE_ENROLLMENTS TABLE (optional)
-- =====================================================
CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, user_id)
);

-- 6. SAMPLE DATA (for testing)
-- =====================================================
-- Insert sample courses
INSERT INTO courses (id, title, description, category_id, price, is_free, image_url, telegram_link, instructor_name, status) VALUES
(UUID(), 'Complete Web Development Bootcamp', 'Learn HTML, CSS, JavaScript, React and Node.js from scratch. Build 10+ real-world projects and become a professional full-stack developer.', 1, 49.99, 0, 'https://images.unsplash.com/photo-1498050108023-c5249f4df085', 'https://t.me/webdev_course', 'John Smith', 'active'),
(UUID(), 'Graphic Design Masterclass', 'Master Adobe Photoshop, Illustrator, and Figma. Learn design principles, color theory, and create stunning visual content.', 2, 39.99, 0, 'https://images.unsplash.com/photo-1626785774573-4b799315345d', 'https://t.me/design_course', 'Sarah Johnson', 'active'),
(UUID(), 'Business Analytics with Python', 'Analyze business data using Python, Pandas, and visualization libraries. Make data-driven decisions for your business.', 3, 0.00, 1, 'https://images.unsplash.com/photo-1551288049-bebda4e38f71', 'https://t.me/analytics_course', 'Michael Chen', 'active'),
(UUID(), 'Digital Marketing 2025', 'Complete guide to SEO, social media marketing, email campaigns, and paid advertising. Grow your business online.', 4, 29.99, 0, 'https://images.unsplash.com/photo-1460925895917-afdab827c52f', 'https://t.me/marketing_course', 'Emma Wilson', 'active')
ON DUPLICATE KEY UPDATE title=title;

-- 7. USEFUL QUERIES
-- =====================================================

-- Get all courses with category names
-- SELECT c.*, cat.name as category_name 
-- FROM courses c 
-- JOIN categories cat ON c.category_id = cat.id 
-- WHERE c.deleted_at IS NULL;

-- Get courses by category
-- SELECT * FROM courses WHERE category_id = 1 AND deleted_at IS NULL;

-- Get statistics
-- SELECT 
--   COUNT(*) as total_courses,
--   SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_courses,
--   SUM(CASE WHEN is_free = 0 THEN 1 ELSE 0 END) as paid_courses,
--   SUM(CASE WHEN is_free = 0 THEN price ELSE 0 END) as total_revenue
-- FROM courses WHERE deleted_at IS NULL;

-- Get category with course count
-- SELECT cat.*, COUNT(c.id) as course_count 
-- FROM categories cat 
-- LEFT JOIN courses c ON cat.id = c.category_id AND c.deleted_at IS NULL 
-- GROUP BY cat.id;

-- =====================================================
-- END OF SCHEMA
-- =====================================================
