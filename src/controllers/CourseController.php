<?php

namespace SkillSwap\Controllers;

use SkillSwap\Models\Course;
use SkillSwap\Models\Application;
use SkillSwap\Services\JWTService;
use SkillSwap\Services\CloudinaryService;
use SkillSwap\Utils\Response;
use SkillSwap\Utils\Validator;

class CourseController {
    public static function getAll(): void {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $category = $_GET['category'] ?? null;
        $status = $_GET['status'] ?? 'approved'; // Only approved courses for public
        $search = $_GET['search'] ?? null;
        
        $courses = Course::getAll($page, $limit, $category, $status, $search);
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
    
    public static function getById(): void {
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Course ID is required');
        
        $course = Course::findById($id);
        if (!$course) Response::notFound('Course not found');
        
        Response::success($course);
    }
    
    public static function create(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser) Response::unauthorized();
        if (!in_array($currentUser->role, ['instructor', 'admin'])) Response::forbidden();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data);
        $validator->required(['title', 'description', 'teacher', 'category', 'price'])
                  ->numeric('price');
        
        if ($validator->fails()) Response::validationError($validator->getErrors());
        
        $data['instructor_id'] = $currentUser->id;
        $courseId = Course::create($data);
        
        if ($courseId) {
            Response::success(['course_id' => $courseId], 'Course created successfully', 201);
        } else {
            Response::serverError('Failed to create course');
        }
    }
    
    public static function update(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser) Response::unauthorized();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Course ID is required');
        
        $course = Course::findById($id);
        if (!$course) Response::notFound();
        
        // Check permissions
        if ($currentUser->role !== 'admin' && $course['instructor_id'] !== $currentUser->id) {
            Response::forbidden();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (Course::update($id, $data)) {
            Response::success(null, 'Course updated successfully');
        } else {
            Response::serverError('Failed to update course');
        }
    }
    
    public static function delete(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser || $currentUser->role !== 'admin') Response::forbidden();
        
        $id = $_GET['id'] ?? null;
        if (!$id) Response::error('Course ID is required');
        
        if (Course::delete($id)) {
            Response::success(null, 'Course deleted successfully');
        } else {
            Response::serverError('Failed to delete course');
        }
    }
    
    public static function purchase(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser) Response::unauthorized();
        
        $data = json_decode(file_get_contents('php://input'), true);
        $courseId = $data['course_id'] ?? null;
        
        if (!$courseId) Response::error('Course ID is required');
        
        $course = Course::findById($courseId);
        if (!$course) Response::notFound('Course not found');
        if ($course['status'] !== 'approved') Response::error('Course is not available');
        
        // Check if already purchased
        if (Application::exists($currentUser->id, $courseId)) {
            Response::error('You have already enrolled in this course');
        }
        
        // Create application (auto-approved as per Q5: A - direct purchase)
        $appId = Application::create($currentUser->id, $courseId);
        
        if ($appId) {
            Response::success(['application_id' => $appId], 'Successfully enrolled in course', 201);
        } else {
            Response::serverError('Failed to enroll');
        }
    }
}
