-- =====================================================
-- CATEGORIES MIGRATION - ONLY NEW CHANGES
-- Railway Skill Swap Database
-- =====================================================

-- 1. CREATE CATEGORIES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. INSERT DEFAULT CATEGORIES
-- =====================================================
INSERT INTO categories (name, slug) VALUES
('Programming', 'programming'),
('Design', 'design'),
('Business', 'business'),
('Marketing', 'marketing')
ON DUPLICATE KEY UPDATE name=name;

-- 3. ADD NEW COLUMNS TO COURSES TABLE (IF NOT EXISTS)
-- =====================================================

-- Check if category_id column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'courses' 
    AND COLUMN_NAME = 'category_id'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE courses ADD COLUMN category_id INT AFTER category', 
    'SELECT "Column category_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if is_free column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'courses' 
    AND COLUMN_NAME = 'is_free'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE courses ADD COLUMN is_free BOOLEAN DEFAULT 0 AFTER price', 
    'SELECT "Column is_free already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if instructor_name column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'courses' 
    AND COLUMN_NAME = 'instructor_name'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE courses ADD COLUMN instructor_name VARCHAR(100) AFTER image_url', 
    'SELECT "Column instructor_name already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if telegram_link column exists, if not add it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'courses' 
    AND COLUMN_NAME = 'telegram_link'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE courses ADD COLUMN telegram_link VARCHAR(255) AFTER instructor_name', 
    'SELECT "Column telegram_link already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. UPDATE EXISTING COURSES (SET DEFAULT CATEGORY)
-- =====================================================
-- Set category_id to 1 (Programming) for existing courses that don't have it
UPDATE courses 
SET category_id = 1 
WHERE category_id IS NULL OR category_id = 0;

-- Update is_free based on price
UPDATE courses 
SET is_free = IF(price = 0 OR price IS NULL, 1, 0)
WHERE is_free IS NULL;

-- 5. ADD FOREIGN KEY CONSTRAINT (IF NOT EXISTS)
-- =====================================================
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'courses' 
    AND CONSTRAINT_NAME = 'fk_courses_category'
);

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE courses ADD CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT', 
    'SELECT "Foreign key fk_courses_category already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. VERIFICATION QUERIES
-- =====================================================

-- Show all tables
SHOW TABLES;

-- Show categories
SELECT * FROM categories;

-- Show courses structure
DESCRIBE courses;

-- Count courses by category
SELECT 
    cat.name as category_name, 
    COUNT(c.id) as course_count 
FROM categories cat 
LEFT JOIN courses c ON cat.id = c.category_id 
GROUP BY cat.id;

-- =====================================================
-- MIGRATION COMPLETE!
-- =====================================================
-- Expected results:
-- 1. categories table created with 4 default categories
-- 2. courses table has new columns: category_id, is_free, instructor_name, telegram_link
-- 3. Existing courses updated with default values
-- 4. Foreign key constraint added
-- =====================================================
