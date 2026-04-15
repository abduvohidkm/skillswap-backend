-- =====================================================
-- SKILL SWAP ACADEMY - DATABASE SCHEMA
-- Created: 2025-11-18
-- Database: MySQL 8.0+
-- =====================================================

-- Create database stripped for serverless Railway deployment

-- =====================================================
-- TABLE: users
-- =====================================================
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'instructor', 'admin') DEFAULT 'user',
    avatar VARCHAR(500) DEFAULT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('active', 'banned') DEFAULT 'active',
    theme VARCHAR(20) DEFAULT 'dark',
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: courses
-- =====================================================
CREATE TABLE courses (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    teacher VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    instructor_id CHAR(36) NOT NULL,
    approved_at DATETIME DEFAULT NULL,
    approved_by CHAR(36) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_instructor (instructor_id),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: applications (Course Enrollments)
-- =====================================================
CREATE TABLE applications (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    student_id CHAR(36) NOT NULL,
    course_id CHAR(36) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    processed_by CHAR(36) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_course (student_id, course_id),
    INDEX idx_status (status),
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_applied_at (applied_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: exam_materials
-- =====================================================
CREATE TABLE exam_materials (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    exam_date DATE NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    proof_link VARCHAR(500) DEFAULT NULL,
    buy_link VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_exam_date (exam_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: purchases (Transaction History)
-- =====================================================
CREATE TABLE purchases (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    item_type ENUM('course', 'exam_material') NOT NULL,
    item_id CHAR(36) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    payment_method VARCHAR(50) DEFAULT 'manual',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_item_type (item_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: password_resets (Future Feature)
-- =====================================================
CREATE TABLE password_resets (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- =====================================================
-- VIEWS
-- =====================================================

-- View: User Statistics
CREATE OR REPLACE VIEW user_stats AS
SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.role,
    u.balance,
    u.is_active,
    u.created_at,
    COUNT(DISTINCT a.id) as courses_enrolled,
    COUNT(DISTINCT p.id) as total_purchases,
    COALESCE(SUM(p.amount), 0) as total_spent
FROM users u
LEFT JOIN applications a ON u.id = a.student_id AND a.status = 'approved'
LEFT JOIN purchases p ON u.id = p.user_id AND p.status = 'completed'
GROUP BY u.id;

-- View: Course Statistics
CREATE OR REPLACE VIEW course_stats AS
SELECT 
    c.id,
    c.title,
    c.teacher,
    c.category,
    c.price,
    c.status,
    c.created_at,
    u.first_name as instructor_first_name,
    u.last_name as instructor_last_name,
    COUNT(DISTINCT a.id) as total_enrollments,
    COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_applications,
    COUNT(CASE WHEN a.status = 'approved' THEN 1 END) as approved_enrollments
FROM courses c
LEFT JOIN users u ON c.instructor_id = u.id
LEFT JOIN applications a ON c.id = a.course_id
GROUP BY c.id;

-- =====================================================
-- (Trigger for auto-approve moved to application logic to avoid PDO syntax errors)
-- INDEXES FOR PERFORMANCE
-- =====================================================
ALTER TABLE users ADD FULLTEXT INDEX ft_user_name (first_name, last_name);
ALTER TABLE courses ADD FULLTEXT INDEX ft_course_search (title, description, teacher);

-- =====================================================
-- END OF SCHEMA
-- =====================================================
