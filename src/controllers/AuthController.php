<?php

namespace SkillSwap\Controllers;

use SkillSwap\Models\User;
use SkillSwap\Services\JWTService;
use SkillSwap\Services\EmailService;
use SkillSwap\Utils\Response;
use SkillSwap\Utils\Validator;

class AuthController {
    /**
     * Register new user
     * POST /api/auth/register
     */
    public static function register(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($data);
        $validator->required(['first_name', 'last_name', 'email', 'phone', 'password'])
                  ->email('email')
                  ->password('password')
                  ->phone('phone');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        // Check if email already exists
        if (User::findByEmail($data['email'])) {
            Response::error('Email already registered', 400);
        }
        
        // Create user (auto-approve as per Q4: A)
        $userId = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => 'user'
        ]);
        
        if (!$userId) {
            Response::serverError('Failed to create user');
        }
        
        // Send welcome email
        $emailService = new EmailService();
        $emailService->sendWelcomeEmail(
            $data['email'],
            $data['first_name'] . ' ' . $data['last_name']
        );
        
        // Get user data
        $user = User::findById($userId);
        
        // Generate JWT token
        $token = JWTService::generate($user);
        
        Response::success([
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token
        ], 'Registration successful', 201);
    }
    
    /**
     * Login user
     * POST /api/auth/login
     */
    public static function login(): void {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($data);
        $validator->required(['email', 'password']);
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        // Verify credentials
        $user = User::verifyPassword($data['email'], $data['password']);
        
        if (!$user) {
            Response::error('Invalid credentials', 401);
        }
        
        // Check if user is banned
        if ($user['is_banned']) {
            Response::error('Your account has been banned', 403);
        }
        
        // Check if user is active
        if (!$user['is_active']) {
            Response::error('Your account is inactive', 403);
        }
        
        // Update last login
        User::updateLastLogin($user['id']);
        
        // Generate JWT token
        $token = JWTService::generate($user);
        
        Response::success([
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'avatar' => $user['avatar'],
                'balance' => $user['balance']
            ],
            'token' => $token
        ], 'Login successful');
    }
    
    /**
     * Get current authenticated user
     * GET /api/auth/me
     */
    public static function me(): void {
        $currentUser = JWTService::getCurrentUser();
        
        if (!$currentUser) {
            Response::unauthorized();
        }
        
        $user = User::findById($currentUser->id);
        
        if (!$user) {
            Response::notFound('User not found');
        }
        
        Response::success([
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'avatar' => $user['avatar'],
            'balance' => $user['balance'],
            'theme' => $user['theme'],
            'created_at' => $user['created_at']
        ]);
    }
    
    /**
     * Logout user (client-side token removal)
     * POST /api/auth/logout
     */
    public static function logout(): void {
        // With JWT, logout is handled client-side by removing the token
        // We could implement token blacklist here if needed
        Response::success(null, 'Logout successful');
    }
    
    /**
     * Refresh JWT token
     * POST /api/auth/refresh
     */
    public static function refresh(): void {
        $currentUser = JWTService::getCurrentUser();
        
        if (!$currentUser) {
            Response::unauthorized();
        }
        
        $user = User::findById($currentUser->id);
        
        if (!$user) {
            Response::unauthorized();
        }
        
        // Generate new token
        $token = JWTService::generate($user);
        
        Response::success([
            'token' => $token
        ], 'Token refreshed');
    }
    
    /**
     * Change password
     * POST /api/auth/change-password
     */
    public static function changePassword(): void {
        $currentUser = JWTService::getCurrentUser();
        
        if (!$currentUser) {
            Response::unauthorized();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($data);
        $validator->required(['current_password', 'new_password'])
                  ->password('new_password');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        // Verify current password
        $user = User::findById($currentUser->id);
        
        if (!password_verify($data['current_password'], $user['password'])) {
            Response::error('Current password is incorrect', 400);
        }
        
        // Update password
        if (User::updatePassword($currentUser->id, $data['new_password'])) {
            Response::success(null, 'Password changed successfully');
        } else {
            Response::serverError('Failed to change password');
        }
    }
}
