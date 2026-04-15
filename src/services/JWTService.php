<?php

namespace SkillSwap\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTService {
    private static string $secret;
    private static int $expire;
    
    public static function init(): void {
        self::$secret = $_ENV['JWT_SECRET'];
        self::$expire = (int)$_ENV['JWT_EXPIRE']; // 86400 = 24 hours
    }
    
    /**
     * Generate JWT token for user
     */
    public static function generate(array $userData): string {
        self::init();
        
        $issuedAt = time();
        $expirationTime = $issuedAt + self::$expire;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user' => [
                'id' => $userData['id'],
                'email' => $userData['email'],
                'role' => $userData['role'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name']
            ]
        ];
        
        return JWT::encode($payload, self::$secret, 'HS256');
    }
    
    /**
     * Verify and decode JWT token
     */
    public static function verify(string $token): ?object {
        try {
            self::init();
            $decoded = JWT::decode($token, new Key(self::$secret, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            error_log("JWT verification failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract token from Authorization header
     */
    public static function extractFromHeader(): ?string {
        $headers = getallheaders();
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Get current authenticated user from token
     */
    public static function getCurrentUser(): ?object {
        $token = self::extractFromHeader();
        
        if (!$token) {
            return null;
        }
        
        $decoded = self::verify($token);
        
        if (!$decoded) {
            return null;
        }
        
        return $decoded->user ?? null;
    }
}
