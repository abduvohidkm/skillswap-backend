-- =====================================================
-- SKILL SWAP ACADEMY - SEED DATA
-- Default Admin User
-- =====================================================

USE skill_swap_academy;

-- Insert default admin user
-- Email: admin@skillswap.com
-- Password: Admin@123 (hashed with bcrypt)
INSERT INTO users (
    id,
    first_name,
    last_name,
    email,
    phone,
    password,
    role,
    is_active,
    created_at
) VALUES (
    UUID(),
    'Admin',
    'User',
    'admin@skillswap.com',
    '+998901234567',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@123
    'admin',
    TRUE,
    NOW()
);

-- Insert sample categories for courses
INSERT INTO exam_materials (
    title,
    description,
    exam_date,
    price,
    image_url,
    proof_link,
    buy_link
) VALUES
(
    'IELTS Writing Task 2 - January 2025',
    'Complete essay questions and model answers for January exam',
    '2025-01-15',
    49.00,
    'https://i.postimg.cc/qq3wQvVV/skilllogo.jpg',
    'https://t.me/skillswapproofs',
    'https://t.me/skillswapadmin'
),
(
    'SAT Math Practice - December 2024',
    'Full practice test with detailed solutions and strategies',
    '2024-12-02',
    59.00,
    'https://i.postimg.cc/qq3wQvVV/skilllogo.jpg',
    'https://t.me/skillswapproofs',
    'https://t.me/skillswapadmin'
),
(
    'TOEFL Speaking Section Guide',
    'Sample responses and scoring rubrics for all speaking tasks',
    '2025-01-20',
    45.00,
    'https://i.postimg.cc/qq3wQvVV/skilllogo.jpg',
    'https://t.me/skillswapproofs',
    'https://t.me/skillswapadmin'
);

-- =====================================================
-- NOTE: To create the default admin password hash
-- Use PHP: password_hash('Admin@123', PASSWORD_BCRYPT)
-- Or online bcrypt generator
-- =====================================================
