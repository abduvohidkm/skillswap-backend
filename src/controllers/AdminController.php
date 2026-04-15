<?php

namespace SkillSwap\Controllers;

use SkillSwap\Models\User;
use SkillSwap\Models\Course;
use SkillSwap\Models\Application;
use SkillSwap\Services\JWTService;
use SkillSwap\Services\EmailService;
use SkillSwap\Utils\Response;
use SkillSwap\Utils\Validator;

class AdminController {
    private static function checkAdmin(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser || $currentUser->role !== 'admin') {
            Response::forbidden('Admin access required');
        }
    }
    
    // Dashboard
    public static function getDashboard(): void {
        self::checkAdmin();
        
        $totalUsers = User::getCount();
        $totalCourses = Course::getCount();
        $pendingApplications = Application::getCount('pending');
        $approvedCourses = Course::getCount('approved');
        
        Response::success([
            'total_users' => $totalUsers,
            'total_courses' => $totalCourses,
            'approved_courses' => $approvedCourses,
            'pending_applications' => $pendingApplications
        ]);
    }
    
    // User Management
    public static function getAllUsers(): void {
        self::checkAdmin();
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $role = $_GET['role'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $users = User::getAll($page, $limit, $role, $search);
        $total = User::getCount($role);
        
        Response::success([
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public static function createUser(): void {
        self::checkAdmin();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data);
        $validator->required(['first_name', 'last_name', 'email', 'password', 'role'])
                  ->email('email')
                  ->password('password')
                  ->enum('role', ['user', 'instructor', 'admin']);
        
        if ($validator->fails()) Response::validationError($validator->getErrors());
        
        if (User::findByEmail($data['email'])) {
            Response::error('Email already exists');
        }
        
        $userId = User::create($data);
        
        if ($userId) {
            Response::success(['user_id' => $userId], 'User created successfully', 201);
        } else {
            Response::serverError('Failed to create user');
        }
    }
    
    public static function updateUser(): void {
        self::checkAdmin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('User ID is required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (User::update($id, $data)) {
            Response::success(null, 'User updated successfully');
        } else {
            Response::serverError('Failed to update user');
        }
    }
    
    public static function banUser(): void {
        self::checkAdmin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('User ID is required');
        
        if (User::ban($id)) {
            Response::success(null, 'User banned successfully');
        } else {
            Response::serverError('Failed to ban user');
        }
    }
    
    public static function unbanUser(): void {
        self::checkAdmin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('User ID is required');
        
        if (User::unban($id)) {
            Response::success(null, 'User unbanned successfully');
        } else {
            Response::serverError('Failed to unban user');
        }
    }
    
    public static function deleteUser(): void {
        self::checkAdmin();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('User ID is required');
        
        if (User::delete($id)) {
            Response::success(null, 'User deleted successfully');
        } else {
            Response::serverError('Failed to delete user');
        }
    }
    
    // Course Management
    public static function getAllCourses(): void {
        self::checkAdmin();
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $status = $_GET['status'] ?? null;
        
        $courses = Course::getAll($page, $limit, null, $status);
        $total = Course::getCount($status);
        
        Response::success([
            'courses' => $courses,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public static function approveCourse(): void {
        self::checkAdmin();
        $currentUser = JWTService::getCurrentUser();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Course ID is required');
        
        if (Course::approve($id, $currentUser->id)) {
            Response::success(null, 'Course approved successfully');
        } else {
            Response::serverError('Failed to approve course');
        }
    }
    
    public static function rejectCourse(): void {
        self::checkAdmin();
        $currentUser = JWTService::getCurrentUser();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Course ID is required');
        
        if (Course::reject($id, $currentUser->id)) {
            Response::success(null, 'Course rejected');
        } else {
            Response::serverError('Failed to reject course');
        }
    }
    
    // Application Management
    public static function getAllApplications(): void {
        self::checkAdmin();
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $status = $_GET['status'] ?? null;
        
        $applications = Application::getAll($page, $limit, $status);
        $total = Application::getCount($status);
        
        Response::success([
            'applications' => $applications,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public static function approveApplication(): void {
        self::checkAdmin();
        $currentUser = JWTService::getCurrentUser();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Application ID is required');
        
        $application = Application::findById($id);
        if (!$application) Response::notFound();
        
        if (Application::approve($id, $currentUser->id)) {
            // Send email notification
            $emailService = new EmailService();
            $emailService->sendCourseApprovedEmail(
                $application['email'],
                $application['first_name'] . ' ' . $application['last_name'],
                $application['course_title']
            );
            
            Response::success(null, 'Application approved');
        } else {
            Response::serverError('Failed to approve');
        }
    }
    
    public static function rejectApplication(): void {
        self::checkAdmin();
        $currentUser = JWTService::getCurrentUser();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Application ID is required');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $notes = $data['notes'] ?? null;
        
        $application = Application::findById($id);
        if (!$application) Response::notFound();
        
        if (Application::reject($id, $currentUser->id, $notes)) {
            // Send email notification
            $emailService = new EmailService();
            $emailService->sendCourseRejectedEmail(
                $application['email'],
                $application['first_name'] . ' ' . $application['last_name'],
                $application['course_title'],
                $notes
            );
            
            Response::success(null, 'Application rejected');
        } else {
            Response::serverError('Failed to reject');
        }
    }
}
