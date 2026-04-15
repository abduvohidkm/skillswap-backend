<?php
/**
 * ============================================
 * SKILL SWAP ACADEMY - BACKEND API
 * Version: 2.2 - FIXED ALL 500 ERRORS
 * Date: 2026-01-25
 * ============================================
 * 
 * FIXES:
 * ✅ Global $input variable
 * ✅ CURL availability check
 * ✅ Consistent UUID/INT patterns
 * ✅ Environment variables for secrets
 * ✅ Better error handling
 */

// ============================================
// ERROR HANDLING & HEADERS
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// 🔥 FIX #1: GLOBAL INPUT VARIABLE
// ============================================
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================
// HELPER FUNCTIONS
// ============================================

function env($key, $default = '') {
    return getenv($key) ?: $default;
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? $_ENV['DB_HOST'] ?? 'mysql.railway.internal';
            if (empty($host) || $host === 'localhost' || $host === '127.0.0.1') {
                $host = 'mysql.railway.internal';
            }
            $port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? $_ENV['DB_PORT'] ?? '3306';
            if (empty($port)) $port = '3306';
            
            $dbname = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? $_ENV['DB_NAME'] ?? 'railway';
            if (empty($dbname)) $dbname = 'railway';
            
            $user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? $_ENV['DB_USER'] ?? 'root';
            if (empty($user)) $user = 'root';
            
            $pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? $_ENV['DB_PASS'] ?? 'QKfxegOUsmciItrOQhFKPNAXXjxfYeOg';
            if (empty($pass) || $pass === 'root') {
                $pass = 'QKfxegOUsmciItrOQhFKPNAXXjxfYeOg';
            }
            
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                $host, $port, $dbname
            );
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false, 
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }
    }
    return $pdo;
}

function jwt_encode($data) {
    $secret = env('JWT_SECRET', 'your-secret-key-change-in-production');
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64_encode(json_encode(array_merge($data, [
        'exp' => time() + 86400,
        'iat' => time()
    ])));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$signature";
}

function jwt_decode($token) {
    $secret = env('JWT_SECRET', 'your-secret-key-change-in-production');
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    
    list($header, $payload, $signature) = $parts;
    
    $validSignature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if ($signature !== $validSignature) return null;
    
    $data = json_decode(base64_decode($payload), true);
    if (!$data || !isset($data['exp']) || $data['exp'] <= time()) return null;
    
    return $data;
}

function get_user() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) return null;
    return jwt_decode($matches[1]);
}

function require_auth() {
    $user = get_user();
    if (!$user) {
        http_response_code(401);
        die(json_encode([
            'success' => false, 
            'message' => 'Unauthorized - Please login'
        ]));
    }
    return $user;
}

function require_admin() {
    $user = require_auth();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die(json_encode([
            'success' => false, 
            'message' => 'Admin access required'
        ]));
    }
    return $user;
}

// ============================================
// 🔥 FIX #2: CURL AVAILABILITY CHECK
// ============================================
function send_email($to, $subject, $html) {
    if (!function_exists('curl_init')) {
        error_log("❌ CURL is not available - cannot send emails");
        throw new Exception("Email service not available");
    }
    
    // 🔥 FIX #4: Environment variable for API key
    $resendApiKey = env('RESEND_API_KEY', 're_6383ihem_64jFxTFJNS4grzYsPc5cp2Ax');
    
    $emailData = [
        'from' => 'SkillSwap Academy <hello@verification.skill-swap.uz>',
        'to' => is_array($to) ? $to : [$to],
        'subject' => $subject,
        'html' => $html
    ];
    
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $resendApiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Resend API error ($httpCode): " . $response);
        throw new Exception("Failed to send email: " . ($error ?: 'Unknown error'));
    }
    
    return true;
}

// ============================================
// REQUEST PARSING
// ============================================

$path = preg_replace('#^/api#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];

try {
    // ============================================
    // HEALTH & DIAGNOSTICS
    // ============================================
    
    if ($path === '/health') {
        die(json_encode([
            'success' => true,
            'message' => 'API is running',
            'php_version' => PHP_VERSION,
            'curl_available' => function_exists('curl_init'),
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
    
    if ($path === '/test-db') {
        db();
        die(json_encode([
            'success' => true,
            'message' => 'Database connected successfully'
        ]));
    }
    
    if ($path === '/setup-db') {
        $pdo = db();
        $sql = file_get_contents(__DIR__ . '/../database/migrations/create_tables.sql');
        if ($sql) {
            $pdo->exec($sql);
            die(json_encode(['success' => true, 'message' => 'Database tables migrated successfully']));
        } else {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Could not read migration script']));
        }
    }
    
    if ($path === '/schema-fix') {
        $pdo = db();
        try {
            // Drop old columns if they exist
            $pdo->exec("ALTER TABLE users DROP COLUMN IF EXISTS is_active");
            $pdo->exec("ALTER TABLE users DROP COLUMN IF EXISTS is_banned");
            // Add status column
            $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'banned') DEFAULT 'active' AFTER role");
            die(json_encode(['success' => true, 'message' => 'Schema status applied successfully']));
        } catch (Exception $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Schema fix failed: ' . $e->getMessage()]));
        }
    }
    
    // ============================================
    // AUTHENTICATION
    // ============================================
    
    // Register new user
    if ($path === '/auth/register' && $method === 'POST') {
        $required = ['email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => "Missing required field: $field"
                ]));
            }
        }
        
        // Validate email
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]));
        }
        
        $db = db();
        
        // Check if email exists
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$input['email']]);
        if ($check->fetch()) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]));
        }
        
        $hash = password_hash($input['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'user', 'active', NOW())
        ");
        $stmt->execute([
            $input['first_name'],
            $input['last_name'],
            $input['email'],
            $input['phone'] ?? '',
            $hash
        ]);
        
        // Retrieve the generated UUID id properly since lastInsertId() fails for UUIDs
        $fetchStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $fetchStmt->execute([$input['email']]);
        $userId = $fetchStmt->fetchColumn();
        $token = jwt_encode([
            'user_id' => $userId,
            'email' => $input['email'],
            'role' => 'user'
        ]);
        
        die(json_encode([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $userId,
                    'email' => $input['email'],
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'role' => 'user'
                ]
            ],
            'message' => 'Registration successful'
        ]));
    }
    
    // ============================================
    // TOKEN VERIFICATION ENDPOINT
    // ============================================
    
    if ($path === '/auth/verify' && $method === 'GET') {
        $user = require_auth();
        
        die(json_encode([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                    'status' => $user['status']
                ]
            ]
        ]));
    }
    
    // ============================================
    // 2FA EMAIL VERIFICATION ENDPOINTS
    // ============================================
    
    // Send verification code via email
    if ($path === '/auth/send-verification-code' && $method === 'POST') {
        try {
            if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Valid email is required'
                ]));
            }
            
            $email = $input['email'];
            $db = db();
            
            // Check if email already exists
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Email already registered'
                ]));
            }
            
            // Generate 6-digit code
            $code = sprintf("%06d", mt_rand(0, 999999));
            
            // Create table if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS verification_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    code VARCHAR(6) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (email),
                    INDEX (expires_at)
                )
            ");
            
            // Delete old codes for this email
            $db->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$email]);
            
            // Insert new code (expires in 5 minutes)
            $expiresAt = date('Y-m-d H:i:s', time() + 300);
            $stmt = $db->prepare("
                INSERT INTO verification_codes (email, code, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$email, $code, $expiresAt]);
            
            // Send email using helper function
            $html = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                        <h1 style='color: white; margin: 0; font-size: 28px;'>SkillSwap Academy</h1>
                    </div>
                    <div style='background: #f7f7f7; padding: 40px 30px; border-radius: 0 0 10px 10px;'>
                        <h2 style='color: #333; margin-top: 0;'>Email Verification</h2>
                        <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                            Thank you for signing up! Please use the verification code below to complete your registration:
                        </p>
                        <div style='background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;'>
                            <div style='color: #667eea; font-size: 42px; font-weight: bold; letter-spacing: 8px; font-family: monospace;'>
                                {$code}
                            </div>
                        </div>
                        <p style='color: #999; font-size: 14px;'>
                            This code will expire in <strong>5 minutes</strong>.
                        </p>
                        <p style='color: #999; font-size: 14px;'>
                            If you didn't request this code, please ignore this email.
                        </p>
                    </div>
                    <div style='text-align: center; padding: 20px; color: #999; font-size: 12px;'>
                        <p>&copy; 2025 SkillSwap Academy. All rights reserved.</p>
                    </div>
                </div>
            ";
            
            send_email($email, 'Your Verification Code - SkillSwap Academy', $html);
            
            error_log("✅ Verification code sent to $email: $code");
            
            die(json_encode([
                'success' => true,
                'message' => 'Verification code sent to your email'
            ]));
            
        } catch (Exception $e) {
            error_log("Send verification code error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
        }
    }
    
    // Verify code and register
    if ($path === '/auth/verify-and-register' && $method === 'POST') {
        try {
            $required = ['email', 'code', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    die(json_encode([
                        'success' => false,
                        'message' => "Missing required field: $field"
                    ]));
                }
            }
            
            $email = $input['email'];
            $code = $input['code'];
            $db = db();
            
            // Verify code
            $stmt = $db->prepare("
                SELECT code, expires_at 
                FROM verification_codes 
                WHERE email = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $storedCode = $stmt->fetch();
            
            if (!$storedCode) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'No verification code found. Please request a new code.'
                ]));
            }
            
            // Check expiration
            if (strtotime($storedCode['expires_at']) < time()) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Verification code expired. Please request a new code.'
                ]));
            }
            
            // Check code match
            if ($storedCode['code'] !== $code) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Invalid verification code'
                ]));
            }
            
            // Code is valid - proceed with registration
            $hash = password_hash($input['password'], PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("
                INSERT INTO users (
                    first_name, last_name, email, phone, password, 
                    role, status, is_verified, created_at, updated_at
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $input['first_name'],
                $input['last_name'],
                $email,
                $input['phone'] ?? null,
                $hash,
                'user',
                'active',
                1
            ]);
            
            $userId = $db->lastInsertId();
            
            // Delete used verification code
            $db->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$email]);
            
            $token = jwt_encode([
                'user_id' => $userId,
                'email' => $email,
                'role' => 'user'
            ]);
            
            error_log("✅ User registered successfully: $email");
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $userId,
                        'email' => $email,
                        'first_name' => $input['first_name'],
                        'last_name' => $input['last_name'],
                        'role' => 'user'
                    ]
                ],
                'message' => 'Registration successful!'
            ]));
            
        } catch (Exception $e) {
            error_log("Verify and register error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ]));
        }
    }
    
    // Resend verification code
    if ($path === '/auth/resend-verification-code' && $method === 'POST') {
        try {
            if (empty($input['email'])) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Email is required'
                ]));
            }
            
            $email = $input['email'];
            $db = db();
            
            // Check rate limit
            $stmt = $db->prepare("
                SELECT created_at 
                FROM verification_codes 
                WHERE email = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $lastCode = $stmt->fetch();
            
            if ($lastCode && strtotime($lastCode['created_at']) > time() - 60) {
                http_response_code(429);
                die(json_encode([
                    'success' => false,
                    'message' => 'Please wait 60 seconds before requesting a new code'
                ]));
            }
            
            // Generate new code
            $code = sprintf("%06d", mt_rand(0, 999999));
            
            // Delete old codes
            $db->prepare("DELETE FROM verification_codes WHERE email = ?")->execute([$email]);
            
            // Insert new code
            $expiresAt = date('Y-m-d H:i:s', time() + 300);
            $stmt = $db->prepare("
                INSERT INTO verification_codes (email, code, expires_at) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$email, $code, $expiresAt]);
            
            // Send email
            $html = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                        <h1 style='color: white; margin: 0; font-size: 28px;'>SkillSwap Academy</h1>
                    </div>
                    <div style='background: #f7f7f7; padding: 40px 30px; border-radius: 0 0 10px 10px;'>
                        <h2 style='color: #333; margin-top: 0;'>New Verification Code</h2>
                        <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                            Here's your new verification code:
                        </p>
                        <div style='background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;'>
                            <div style='color: #667eea; font-size: 42px; font-weight: bold; letter-spacing: 8px; font-family: monospace;'>
                                {$code}
                            </div>
                        </div>
                        <p style='color: #999; font-size: 14px;'>
                            This code will expire in <strong>5 minutes</strong>.
                        </p>
                    </div>
                </div>
            ";
            
            send_email($email, 'Your New Verification Code - SkillSwap Academy', $html);
            
            error_log("✅ Verification code resent to $email: $code");
            
            die(json_encode([
                'success' => true,
                'message' => 'New verification code sent'
            ]));
            
        } catch (Exception $e) {
            error_log("Resend code error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to resend code'
            ]));
        }
    }
    
    // ============================================
    // LOGIN WITH 2FA EMAIL VERIFICATION
    // ============================================
    
    // Step 1: Verify credentials and send code
    if ($path === '/auth/login-send-code' && $method === 'POST') {
        try {
            if (empty($input['email']) || empty($input['password'])) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Email and password are required'
                ]));
            }
            
            $db = db();
            $stmt = $db->prepare("
                SELECT id, first_name, last_name, email, password, role, status 
                FROM users 
                WHERE email = ?
            ");
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($input['password'], $user['password'])) {
                http_response_code(401);
                die(json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ]));
            }
            
            if (($user['status'] ?? '') === 'banned') {
                http_response_code(403);
                die(json_encode([
                    'success' => false,
                    'message' => 'Your account has been banned'
                ]));
            }
            
            // Generate verification code
            $code = sprintf("%06d", mt_rand(0, 999999));
            $email = $user['email'];
            
            // Create table if needed
            $db->exec("
                CREATE TABLE IF NOT EXISTS login_verification_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    code VARCHAR(6) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX (user_id),
                    INDEX (email),
                    INDEX (expires_at)
                )
            ");
            
            // Delete old codes
            $db->prepare("DELETE FROM login_verification_codes WHERE user_id = ?")->execute([$user['id']]);
            
            // Insert new code
            $expiresAt = date('Y-m-d H:i:s', time() + 300);
            $stmt = $db->prepare("
                INSERT INTO login_verification_codes (user_id, email, code, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $email, $code, $expiresAt]);
            
            // Send email
            $html = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                        <h1 style='color: white; margin: 0; font-size: 28px;'>SkillSwap Academy</h1>
                    </div>
                    <div style='background: #f7f7f7; padding: 40px 30px; border-radius: 0 0 10px 10px;'>
                        <h2 style='color: #333; margin-top: 0;'>Login Verification</h2>
                        <p style='color: #666; font-size: 16px; line-height: 1.6;'>
                            Hello <strong>{$user['first_name']}</strong>,<br><br>
                            Someone is trying to login to your account. Please use the verification code below to complete your login:
                        </p>
                        <div style='background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;'>
                            <div style='color: #667eea; font-size: 42px; font-weight: bold; letter-spacing: 8px; font-family: monospace;'>
                                {$code}
                            </div>
                        </div>
                        <p style='color: #999; font-size: 14px;'>
                            This code will expire in <strong>5 minutes</strong>.
                        </p>
                        <p style='color: #999; font-size: 14px;'>
                            If you didn't try to login, please ignore this email and secure your account.
                        </p>
                    </div>
                    <div style='text-align: center; padding: 20px; color: #999; font-size: 12px;'>
                        <p>&copy; 2025 SkillSwap Academy. All rights reserved.</p>
                    </div>
                </div>
            ";
            
            send_email($email, 'Your Login Verification Code - SkillSwap Academy', $html);
            
            error_log("✅ Login verification code sent to $email: $code");
            
            die(json_encode([
                'success' => true,
                'message' => 'Verification code sent to your email',
                'data' => [
                    'user_id' => $user['id'],
                    'email' => $email
                ]
            ]));
            
        } catch (Exception $e) {
            error_log("Login send code error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to send verification code'
            ]));
        }
    }
    
    // Step 2: Verify code and complete login
    if ($path === '/auth/login-verify-code' && $method === 'POST') {
        try {
            if (empty($input['email']) || empty($input['code'])) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Email and code are required'
                ]));
            }
            
            $email = $input['email'];
            $code = $input['code'];
            $db = db();
            
            // Get user
            $stmt = $db->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                die(json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]));
            }
            
            // Verify code
            $stmt = $db->prepare("
                SELECT code, expires_at 
                FROM login_verification_codes 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user['id']]);
            $storedCode = $stmt->fetch();
            
            if (!$storedCode) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'No verification code found'
                ]));
            }
            
            // Check expiration
            if (strtotime($storedCode['expires_at']) < time()) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Verification code expired'
                ]));
            }
            
            // Check code match
            if ($storedCode['code'] !== $code) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Invalid verification code'
                ]));
            }
            
            // Code is valid - complete login
            $token = jwt_encode([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user'
            ]);
            
            // Delete used code
            $db->prepare("DELETE FROM login_verification_codes WHERE user_id = ?")->execute([$user['id']]);
            
            error_log("✅ User logged in successfully: $email");
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'name' => $user['first_name'] . ' ' . $user['last_name'],
                        'role' => $user['role'] ?? 'user'
                    ]
                ],
                'message' => 'Login successful!'
            ]));
            
        } catch (Exception $e) {
            error_log("Login verify code error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Login failed'
            ]));
        }
    }
    
    // Resend login verification code
    if ($path === '/auth/login-resend-code' && $method === 'POST') {
        try {
            if (empty($input['email'])) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Email is required'
                ]));
            }
            
            $email = $input['email'];
            $db = db();
            
            // Get user
            $stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                die(json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]));
            }
            
            // Check rate limit
            $stmt = $db->prepare("
                SELECT created_at 
                FROM login_verification_codes 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user['id']]);
            $lastCode = $stmt->fetch();
            
            if ($lastCode && strtotime($lastCode['created_at']) > time() - 60) {
                http_response_code(429);
                die(json_encode([
                    'success' => false,
                    'message' => 'Please wait 60 seconds before requesting a new code'
                ]));
            }
            
            // Generate new code
            $code = sprintf("%06d", mt_rand(0, 999999));
            
            // Delete old codes
            $db->prepare("DELETE FROM login_verification_codes WHERE user_id = ?")->execute([$user['id']]);
            
            // Insert new code
            $expiresAt = date('Y-m-d H:i:s', time() + 300);
            $stmt = $db->prepare("
                INSERT INTO login_verification_codes (user_id, email, code, expires_at) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $email, $code, $expiresAt]);
            
            // Send email
            $html = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px 10px 0 0; text-align: center;'>
                        <h1 style='color: white; margin: 0; font-size: 28px;'>SkillSwap Academy</h1>
                    </div>
                    <div style='background: #f7f7f7; padding: 40px 30px; border-radius: 0 0 10px 10px;'>
                        <h2 style='color: #333; margin-top: 0;'>New Login Code</h2>
                        <p style='color: #666; font-size: 16px;'>
                            Hello <strong>{$user['first_name']}</strong>,<br><br>
                            Here's your new login verification code:
                        </p>
                        <div style='background: white; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;'>
                            <div style='color: #667eea; font-size: 42px; font-weight: bold; letter-spacing: 8px; font-family: monospace;'>
                                {$code}
                            </div>
                        </div>
                        <p style='color: #999; font-size: 14px;'>
                            This code will expire in <strong>5 minutes</strong>.
                        </p>
                    </div>
                </div>
            ";
            
            send_email($email, 'New Login Verification Code - SkillSwap Academy', $html);
            
            error_log("✅ Login verification code resent to $email: $code");
            
            die(json_encode([
                'success' => true,
                'message' => 'New verification code sent'
            ]));
            
        } catch (Exception $e) {
            error_log("Login resend code error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to resend code'
            ]));
        }
    }
    
    // OLD Login endpoint (backward compatibility)
    if ($path === '/auth/login' && $method === 'POST') {
        if (empty($input['email']) || empty($input['password'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Email and password are required'
            ]));
        }
        
        $db = db();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, password, role, status 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$input['email']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($input['password'], $user['password'])) {
            http_response_code(401);
            die(json_encode([
                'success' => false,
                'message' => 'Invalid email or password'
            ]));
        }
        
        if (($user['status'] ?? '') === 'banned') {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'Your account has been banned'
            ]));
        }
        
        $token = jwt_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'user'
        ]);
        
        die(json_encode([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role'] ?? 'user'
                ]
            ],
            'message' => 'Login successful'
        ]));
    }
    
    // Get current user info
    if ($path === '/auth/me') {
        $user = require_auth();
        $db = db();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, role, status, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$user['user_id']]);
        $userData = $stmt->fetch();
        
        if (!$userData) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'User not found'
            ]));
        }
        
        die(json_encode([
            'success' => true,
            'data' => ['user' => $userData]
        ]));
    }
    
    // ============================================
    // PUBLIC COURSES
    // ============================================
    
    // Get all active courses (public)
    if ($path === '/courses' && $method === 'GET') {
        $db = db();
        $courses = $db->query("
            SELECT * FROM courses 
            WHERE status = 'active' 
            ORDER BY created_at DESC
        ")->fetchAll();
        
        die(json_encode([
            'success' => true,
            'data' => [
                'courses' => $courses,
                'total' => count($courses)
            ]
        ]));
    }
    
    // Get single course by ID (public)
    if (preg_match('#^/courses/(\d+)$#', $path, $matches) && $method === 'GET') {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND status = 'active'");
        $stmt->execute([$matches[1]]);
        $course = $stmt->fetch();
        
        if (!$course) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'Course not found'
            ]));
        }
        
        die(json_encode([
            'success' => true,
            'data' => ['course' => $course]
        ]));
    }

    // ============================================
    // PUBLIC APPLICATIONS ENDPOINT
    // ============================================

    if ($path === '/applications' && $method === 'POST') {
        try {
            // Validate required fields
            $required = ['fullName', 'email', 'courseTitle', 'category', 'description', 'experience', 'price'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || trim($input[$field]) === '') {
                    http_response_code(400);
                    die(json_encode([
                        'success' => false,
                        'message' => "Missing required field: $field"
                    ]));
                }
            }
            
            // Validate email
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Invalid email format'
                ]));
            }
            
            // Validate price
            $price = floatval($input['price']);
            if ($price < 0) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Price must be a positive number'
                ]));
            }
            
            $db = db();
            
            // Get user_id if logged in (optional)
            $userId = null;
            $user = get_user();
            if ($user) {
                $userId = $user['user_id'];
            }
            
            // Insert application
            $stmt = $db->prepare("
                INSERT INTO applications (
                    user_id, 
                    full_name, 
                    email, 
                    phone, 
                    course_title, 
                    category, 
                    description, 
                    experience, 
                    price, 
                    status, 
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            
            $stmt->execute([
                $userId,
                $input['fullName'],
                $input['email'],
                $input['phone'] ?? null,
                $input['courseTitle'],
                $input['category'],
                $input['description'],
                $input['experience'],
                $price
            ]);
            
            $applicationId = $db->lastInsertId();
            
            error_log("✅ Application created - ID: $applicationId");
            
            die(json_encode([
                'success' => true,
                'data' => ['application_id' => $applicationId],
                'message' => 'Application submitted successfully! We will contact you within 2-3 business days.'
            ]));
            
        } catch (Exception $e) {
            error_log("❌ Application error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to submit application'
            ]));
        }
    }
    
    // ============================================
    // ADMIN - DASHBOARD
    // ============================================
    
    if ($path === '/admin/dashboard' && $method === 'GET') {
        require_admin();
        $db = db();
        
        try {
            $total_users = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $total_courses = (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
            
            try {
                $total_applications = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
                $pending_applications = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
            } catch (Exception $e) {
                $total_applications = 0;
                $pending_applications = 0;
            }
            
            try {
                $total_revenue = (float)$db->query("SELECT COALESCE(SUM(price), 0) FROM courses WHERE is_free = 0")->fetchColumn();
            } catch (Exception $e) {
                $total_revenue = 0;
            }
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'total_users' => $total_users,
                    'total_courses' => $total_courses,
                    'total_applications' => $total_applications,
                    'pending_applications' => $pending_applications,
                    'total_revenue' => $total_revenue
                ]
            ]));
        } catch (Exception $e) {
            error_log("Dashboard error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to fetch dashboard data'
            ]));
        }
    }
    
    if ($path === '/admin/dashboard/stats' && $method === 'GET') {
        require_admin();
        $db = db();
        
        try {
            $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $totalCourses = (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
            $activeCourses = (int)$db->query("SELECT COUNT(*) FROM courses WHERE status = 'active'")->fetchColumn();
            
            try {
                $totalApplications = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
                $pendingApplications = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
            } catch (Exception $e) {
                $totalApplications = 0;
                $pendingApplications = 0;
            }
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'totalUsers' => $totalUsers,
                    'totalCourses' => $totalCourses,
                    'activeCourses' => $activeCourses,
                    'totalApplications' => $totalApplications,
                    'pendingApplications' => $pendingApplications
                ]
            ]));
        } catch (Exception $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics'
            ]));
        }
    }
    
    // ============================================
    // ADMIN - USERS MANAGEMENT
    // ============================================
    
    // Get all users
    if ($path === '/admin/users' && $method === 'GET') {
        require_admin();
        $db = db();
        $users = $db->query("
            SELECT id, first_name, last_name, email, phone, role, status, created_at 
            FROM users 
            ORDER BY created_at DESC
        ")->fetchAll();
        
        die(json_encode([
            'success' => true,
            'data' => ['users' => $users]
        ]));
    }
    
    // Create new user (Admin)
    if ($path === '/admin/users' && $method === 'POST') {
        require_admin();
        
        // Validation
        if (empty($input['email']) || empty($input['password'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Email and password are required'
            ]));
        }
        
        // Validate role
        $role = $input['role'] ?? 'user';
        if (!in_array($role, ['user', 'admin', 'instructor'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Invalid role. Must be user, admin, or instructor'
            ]));
        }
        
        $db = db();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]));
        }
        
        try {
            // Parse names
            $firstName = $input['first_name'] ?? '';
            $lastName = $input['last_name'] ?? '';
            
            if (empty($firstName) && !empty($input['full_name'])) {
                $nameParts = explode(' ', trim($input['full_name']), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';
            }
            
            $hashedPassword = password_hash($input['password'], PASSWORD_BCRYPT);
            
            $stmt = $db->prepare("
                INSERT INTO users (
                    first_name, last_name, email, phone, password, 
                    role, status, is_verified, created_at, updated_at
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $firstName,
                $lastName,
                $input['email'],
                $input['phone'] ?? $input['phone_number'] ?? null,
                $hashedPassword,
                $role,
                'active',
                1
            ]);
            
            $userId = $db->lastInsertId();
            
            $stmt = $db->prepare("
                SELECT id, first_name, last_name, email, phone, role, status, created_at 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            die(json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'data' => ['user' => $user]
            ]));
            
        } catch (PDOException $e) {
            error_log("Failed to create user: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ]));
        }
    }
    
    // 🔥 FIX #3: Consistent ID pattern (support both INT and UUID)
    // Get single user
    if (preg_match('#^/admin/users/([0-9a-f\-]+)$#', $path, $matches) && $method === 'GET') {
        require_admin();
        $db = db();
        
        $userId = $matches[1];
        
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, role, status, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'User not found'
            ]));
        }
        
        die(json_encode([
            'success' => true,
            'data' => $user
        ]));
    }
    
    // Update user
    if (preg_match('#^/admin/users/([0-9a-f\-]+)$#', $path, $matches) && $method === 'PUT') {
        require_admin();
        $db = db();
        
        // Validation
        $errors = [];
        if (!isset($input['first_name']) || strlen($input['first_name']) < 2) {
            $errors[] = 'First name is required (min 2 characters)';
        }
        if (!isset($input['last_name']) || strlen($input['last_name']) < 2) {
            $errors[] = 'Last name is required (min 2 characters)';
        }
        if (!isset($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        if (!isset($input['role']) || !in_array($input['role'], ['user', 'admin'])) {
            $errors[] = 'Role must be user or admin';
        }
        if (!isset($input['status']) || !in_array($input['status'], ['active', 'banned'])) {
            $errors[] = 'Status must be active or banned';
        }
        
        if ($errors) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => implode(', ', $errors)
            ]));
        }
        
        // Check if email is taken
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$input['email'], $matches[1]]);
        if ($stmt->fetch()) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]));
        }
        
        $stmt = $db->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, role = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $input['first_name'],
            $input['last_name'],
            $input['email'],
            $input['phone'] ?? null,
            $input['role'],
            $input['status'],
            $matches[1]
        ]);
        
        die(json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]));
    }
    
    // Delete user
    if (preg_match('#^/admin/users/([0-9a-f\-]+)$#', $path, $matches) && $method === 'DELETE') {
        require_admin();
        $db = db();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]));
    }
    
    // Ban user
    if (preg_match('#^/admin/users/([0-9a-f\-]+)/ban$#', $path, $matches) && $method === 'POST') {
        require_admin();
        $db = db();
        $stmt = $db->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'User banned successfully'
        ]));
    }
    
    // Unban user
    if (preg_match('#^/admin/users/([0-9a-f\-]+)/unban$#', $path, $matches) && $method === 'POST') {
        require_admin();
        $db = db();
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'User unbanned successfully'
        ]));
    }
    
    // Bulk delete users
    if ($path === '/admin/users/bulk-delete' && $method === 'POST') {
        require_admin();
        $db = db();
        
        if (!isset($input['user_ids']) || !is_array($input['user_ids']) || empty($input['user_ids'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'user_ids array is required'
            ]));
        }
        
        $placeholders = implode(',', array_fill(0, count($input['user_ids']), '?'));
        $stmt = $db->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->execute($input['user_ids']);
        
        die(json_encode([
            'success' => true,
            'message' => 'Users deleted successfully'
        ]));
    }
    
    // ============================================
    // ADMIN - COURSES MANAGEMENT
    // ============================================
    
    // Get all courses
    if ($path === '/admin/courses' && $method === 'GET') {
        require_admin();
        $db = db();
        
        try {
            $courses = $db->query("
                SELECT c.*, 
                       COALESCE(cat.name, 'Uncategorized') as category_name 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id 
                ORDER BY c.created_at DESC
            ")->fetchAll();
        } catch (Exception $e) {
            $courses = $db->query("
                SELECT * FROM courses 
                ORDER BY created_at DESC
            ")->fetchAll();
        }
        
        die(json_encode([
            'success' => true,
            'data' => ['courses' => $courses]
        ]));
    }
    
    // Get single course
    if (preg_match('#^/admin/courses/(\d+)$#', $path, $matches) && $method === 'GET') {
        require_admin();
        $db = db();
        $courseId = $matches[1];
        
        try {
            $stmt = $db->prepare("
                SELECT c.*, 
                       COALESCE(cat.name, 'Uncategorized') as category_name,
                       (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as students_count
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$courseId]);
            $course = $stmt->fetch();
        } catch (PDOException $e) {
            $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            $course = $stmt->fetch();
            if ($course) {
                $course['category_name'] = 'Uncategorized';
                $course['students_count'] = 0;
            }
        }
        
        if (!$course) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'Course not found'
            ]));
        }
        
        die(json_encode([
            'success' => true,
            'data' => ['course' => $course]
        ]));
    }
    
    // Create course
    if ($path === '/admin/courses' && $method === 'POST') {
        require_admin();
        
        $required = ['name', 'description'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => "Missing required field: $field"
                ]));
            }
        }
        
        $db = db();
        $stmt = $db->prepare("
            INSERT INTO courses (
                name, description, image_url, price, is_free, 
                category_id, instructor_name, telegram_link, 
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        
        $isFree = ($input['is_free'] ?? true) ? 1 : 0;
        $price = $isFree ? 0 : floatval($input['price'] ?? 0);
        
        $stmt->execute([
            $input['name'],
            $input['description'],
            $input['image_url'] ?? '',
            $price,
            $isFree,
            $input['category_id'] ?? null,
            $input['instructor_name'] ?? '',
            $input['telegram_link'] ?? ''
        ]);
        
        $courseId = $db->lastInsertId();
        
        die(json_encode([
            'success' => true,
            'data' => ['course_id' => $courseId],
            'message' => 'Course created successfully'
        ]));
    }
    
    // Update course
    if (preg_match('#^/admin/courses/(\d+)$#', $path, $matches) && $method === 'PUT') {
        require_admin();
        $courseId = $matches[1];
        
        try {
            $db = db();
            
            // Check if exists
            $check = $db->prepare("SELECT id FROM courses WHERE id = ?");
            $check->execute([$courseId]);
            if (!$check->fetch()) {
                http_response_code(404);
                die(json_encode([
                    'success' => false,
                    'message' => 'Course not found'
                ]));
            }
            
            $updates = [];
            $params = [];
            
            $allowedFields = [
                'name', 'description', 'image_url', 'price', 
                'is_free', 'category_id', 'instructor_name', 
                'telegram_link', 'status'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    if ($field === 'is_free') {
                        $params[] = $input[$field] ? 1 : 0;
                    } else if ($field === 'category_id') {
                        $params[] = $input[$field] === '' || $input[$field] === null ? null : intval($input[$field]);
                    } else if ($field === 'price') {
                        $params[] = floatval($input[$field]);
                    } else {
                        $params[] = $input[$field];
                    }
                }
            }
            
            // Auto-sync is_free based on price
            if (isset($input['price']) && !isset($input['is_free'])) {
                $price = floatval($input['price']);
                $updates[] = "is_free = ?";
                $params[] = $price > 0 ? 0 : 1;
            }
            
            if (empty($updates)) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'No fields to update'
                ]));
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $courseId;
            
            $sql = "UPDATE courses SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            die(json_encode([
                'success' => true,
                'message' => 'Course updated successfully'
            ]));
            
        } catch (Exception $e) {
            error_log("❌ Error updating course: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to update course'
            ]));
        }
    }
    
    // Delete course
    if (preg_match('#^/admin/courses/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        require_admin();
        $db = db();
        
        $stmt = $db->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'Course deleted successfully'
        ]));
    }
    
    // Bulk delete courses
    if ($path === '/admin/courses/bulk-delete' && $method === 'POST') {
        require_admin();
        
        if (empty($input['course_ids']) || !is_array($input['course_ids'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'course_ids array is required'
            ]));
        }
        
        $db = db();
        $placeholders = implode(',', array_fill(0, count($input['course_ids']), '?'));
        $stmt = $db->prepare("DELETE FROM courses WHERE id IN ($placeholders)");
        $stmt->execute($input['course_ids']);
        
        die(json_encode([
            'success' => true,
            'message' => count($input['course_ids']) . ' courses deleted successfully'
        ]));
    }
    
    // Bulk update status
    if ($path === '/admin/courses/bulk-status' && $method === 'POST') {
        require_admin();
        
        if (empty($input['course_ids']) || !is_array($input['course_ids'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'course_ids array is required'
            ]));
        }
        
        $status = $input['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Invalid status'
            ]));
        }
        
        $db = db();
        $placeholders = implode(',', array_fill(0, count($input['course_ids']), '?'));
        $stmt = $db->prepare("
            UPDATE courses 
            SET status = ?, updated_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $params = array_merge([$status], $input['course_ids']);
        $stmt->execute($params);
        
        die(json_encode([
            'success' => true,
            'message' => count($input['course_ids']) . " courses updated to $status"
        ]));
    }
    
    // Update status
    if (preg_match('#^/admin/courses/(\d+)/status$#', $path, $matches) && $method === 'PUT') {
        require_admin();
        $db = db();
        
        if (empty($input['status']) || !in_array($input['status'], ['active', 'inactive'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Invalid status'
            ]));
        }
        
        $stmt = $db->prepare("UPDATE courses SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$input['status'], $matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'Course status updated successfully'
        ]));
    }
    
    // ============================================
    // ADMIN - APPLICATIONS
    // ============================================
    
    // Get all applications
    if ($path === '/admin/applications' && $method === 'GET') {
        require_admin();
        $db = db();
        
        try {
            $apps = $db->query("
                SELECT a.* 
                FROM applications a 
                ORDER BY a.created_at DESC
            ")->fetchAll();
            
            die(json_encode([
                'success' => true,
                'data' => ['applications' => $apps]
            ]));
        } catch (Exception $e) {
            error_log("Applications fetch error: " . $e->getMessage());
            die(json_encode([
                'success' => true,
                'data' => ['applications' => []]
            ]));
        }
    }
    
    // Approve application
    if (preg_match('#^/admin/applications/(\d+)/approve$#', $path, $matches) && $method === 'POST') {
        require_admin();
        $db = db();
        $stmt = $db->prepare("UPDATE applications SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'Application approved successfully'
        ]));
    }
    
    // Reject application
    if (preg_match('#^/admin/applications/(\d+)/reject$#', $path, $matches) && $method === 'POST') {
        require_admin();
        $db = db();
        $stmt = $db->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'Application rejected successfully'
        ]));
    }

    // Delete application
    if (preg_match('#^/admin/applications/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        require_admin();
        $db = db();
        
        $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$matches[1]]);
        
        die(json_encode([
            'success' => true,
            'message' => 'Application deleted successfully'
        ]));
    }

    // Bulk delete applications
    if ($path === '/admin/applications/bulk-delete' && $method === 'POST') {
        require_admin();
        
        if (empty($input['application_ids']) || !is_array($input['application_ids'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'application_ids array is required'
            ]));
        }
        
        $db = db();
        $placeholders = implode(',', array_fill(0, count($input['application_ids']), '?'));
        $stmt = $db->prepare("DELETE FROM applications WHERE id IN ($placeholders)");
        $stmt->execute($input['application_ids']);
        
        die(json_encode([
            'success' => true,
            'message' => count($input['application_ids']) . ' application(s) deleted successfully'
        ]));
    }

    // ============================================
    // ADMIN - CATEGORIES
    // ============================================

    // Get all categories
    if ($path === '/admin/categories' && $method === 'GET') {
        require_admin();
        $db = db();
        
        try {
            $categories = $db->query("
                SELECT cat.*, 
                       COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                GROUP BY cat.id 
                ORDER BY cat.name ASC
            ")->fetchAll();
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'total' => count($categories)
                ]
            ]));
        } catch (Exception $e) {
            error_log("Categories fetch error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ]));
        }
    }

    // Get single category
    if (preg_match('#^/admin/categories/(\d+)$#', $path, $matches) && $method === 'GET') {
        require_admin();
        $db = db();
        $categoryId = $matches[1];
        
        try {
            $stmt = $db->prepare("
                SELECT cat.*, 
                       COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                WHERE cat.id = ? 
                GROUP BY cat.id
            ");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch();
            
            if (!$category) {
                http_response_code(404);
                die(json_encode([
                    'success' => false,
                    'message' => 'Category not found'
                ]));
            }
            
            die(json_encode([
                'success' => true,
                'data' => ['category' => $category]
            ]));
        } catch (Exception $e) {
            error_log("Category fetch error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to fetch category'
            ]));
        }
    }

    // Create category
    if ($path === '/admin/categories' && $method === 'POST') {
        require_admin();
        
        if (empty($input['name'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Category name is required'
            ]));
        }
        
        $name = trim($input['name']);
        
        if (strlen($name) < 3 || strlen($name) > 50) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Category name must be between 3 and 50 characters'
            ]));
        }
        
        $db = db();
        
        // Check if exists
        $check = $db->prepare("SELECT id FROM categories WHERE name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Category with this name already exists'
            ]));
        }
        
        // Create slug
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Check slug
        $checkSlug = $db->prepare("SELECT id FROM categories WHERE slug = ?");
        $checkSlug->execute([$slug]);
        if ($checkSlug->fetch()) {
            $slug .= '-' . substr(md5(uniqid()), 0, 6);
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO categories (name, slug, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt->execute([$name, $slug]);
            
            $categoryId = $db->lastInsertId();
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'category_id' => $categoryId,
                    'name' => $name,
                    'slug' => $slug
                ],
                'message' => 'Category created successfully'
            ]));
        } catch (Exception $e) {
            error_log("Category creation error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to create category'
            ]));
        }
    }

    // Update category
    if (preg_match('#^/admin/categories/(\d+)$#', $path, $matches) && $method === 'PUT') {
        require_admin();
        $categoryId = $matches[1];
        
        if (empty($input['name'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Category name is required'
            ]));
        }
        
        $name = trim($input['name']);
        
        if (strlen($name) < 3 || strlen($name) > 50) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Category name must be between 3 and 50 characters'
            ]));
        }
        
        $db = db();
        
        // Check if exists
        $check = $db->prepare("SELECT id FROM categories WHERE id = ?");
        $check->execute([$categoryId]);
        if (!$check->fetch()) {
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'message' => 'Category not found'
            ]));
        }
        
        // Check if name taken
        $checkName = $db->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $checkName->execute([$name, $categoryId]);
        if ($checkName->fetch()) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'Category with this name already exists'
            ]));
        }
        
        // Create slug
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        try {
            $stmt = $db->prepare("
                UPDATE categories 
                SET name = ?, slug = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $categoryId]);
            
            die(json_encode([
                'success' => true,
                'message' => 'Category updated successfully'
            ]));
        } catch (Exception $e) {
            error_log("Category update error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to update category'
            ]));
        }
    }

    // Delete category
    if (preg_match('#^/admin/categories/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        require_admin();
        $categoryId = $matches[1];
        $db = db();
        
        try {
            // Check if has courses
            $stmt = $db->prepare("
                SELECT cat.*, COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                WHERE cat.id = ? 
                GROUP BY cat.id
            ");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch();
            
            if (!$category) {
                http_response_code(404);
                die(json_encode([
                    'success' => false,
                    'message' => 'Category not found'
                ]));
            }
            
            if ($category['course_count'] > 0) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => "Cannot delete category with {$category['course_count']} course(s)"
                ]));
            }
            
            $deleteStmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $deleteStmt->execute([$categoryId]);
            
            die(json_encode([
                'success' => true,
                'message' => 'Category deleted successfully'
            ]));
        } catch (Exception $e) {
            error_log("Category deletion error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to delete category'
            ]));
        }
    }

    // Bulk delete categories
    if ($path === '/admin/categories/bulk-delete' && $method === 'POST') {
        require_admin();
        
        if (empty($input['category_ids']) || !is_array($input['category_ids'])) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'message' => 'category_ids array is required'
            ]));
        }
        
        $db = db();
        
        try {
            // Check which have courses
            $placeholders = implode(',', array_fill(0, count($input['category_ids']), '?'));
            $stmt = $db->prepare("
                SELECT cat.id, cat.name, COUNT(c.id) as course_count 
                FROM categories cat 
                LEFT JOIN courses c ON cat.id = c.category_id 
                WHERE cat.id IN ($placeholders) 
                GROUP BY cat.id
            ");
            $stmt->execute($input['category_ids']);
            $categories = $stmt->fetchAll();
            
            $cannotDelete = [];
            $canDelete = [];
            
            foreach ($categories as $cat) {
                if ($cat['course_count'] > 0) {
                    $cannotDelete[] = $cat['name'] . " ({$cat['course_count']} courses)";
                } else {
                    $canDelete[] = $cat['id'];
                }
            }
            
            if (!empty($cannotDelete)) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'Some categories cannot be deleted: ' . implode(', ', $cannotDelete)
                ]));
            }
            
            if (empty($canDelete)) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'message' => 'No categories to delete'
                ]));
            }
            
            $placeholders = implode(',', array_fill(0, count($canDelete), '?'));
            $deleteStmt = $db->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
            $deleteStmt->execute($canDelete);
            
            die(json_encode([
                'success' => true,
                'message' => count($canDelete) . ' categories deleted successfully'
            ]));
        } catch (Exception $e) {
            error_log("Bulk category deletion error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to delete categories'
            ]));
        }
    }

    // ============================================
    // PUBLIC CATEGORIES
    // ============================================

    if ($path === '/categories' && $method === 'GET') {
        $db = db();
        
        try {
            $categories = $db->query("
                SELECT id, name, slug, created_at 
                FROM categories 
                ORDER BY name ASC
            ")->fetchAll();
            
            die(json_encode([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'total' => count($categories)
                ]
            ]));
        } catch (Exception $e) {
            error_log("Categories fetch error: " . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ]));
        }
    }
    
    // ============================================
    // 404 - ENDPOINT NOT FOUND
    // ============================================
    
    http_response_code(404);
    die(json_encode([
        'success' => false,
        'message' => 'Endpoint not found',
        'path' => $path,
        'method' => $method
    ]));
    
} catch (Throwable $e) {
    error_log("Unhandled Exception: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]));
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]));
}
