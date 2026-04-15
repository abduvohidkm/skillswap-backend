<?php

namespace SkillSwap\Controllers;

use SkillSwap\Models\User;
use SkillSwap\Services\JWTService;
use SkillSwap\Services\CloudinaryService;
use SkillSwap\Utils\Response;
use SkillSwap\Utils\Validator;

class UserController {
    public static function getProfile(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser) Response::unauthorized();
        
        $user = User::findById($currentUser->id);
        if (!$user) Response::notFound();
        
        $stats = User::getStats($currentUser->id);
        
        Response::success([
            'user' => $user,
            'stats' => $stats
        ]);
    }
    
    public static function updateProfile(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser) Response::unauthorized();
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($data);
        if (isset($data['email'])) $validator->email('email');
        if (isset($data['phone'])) $validator->phone('phone');
        
        if ($validator->fails()) Response::validationError($validator->getErrors());
        
        if (User::update($currentUser->id, $data)) {
            Response::success(null, 'Profile updated successfully');
        } else {
            Response::serverError('Failed to update profile');
        }
    }
    
    public static function uploadAvatar(): void {
        $currentUser = JWTService::getCurrentUser();
        if (!$currentUser) Response::unauthorized();
        
        if (!isset($_FILES['avatar'])) Response::error('No file uploaded');
        
        $result = CloudinaryService::uploadImage($_FILES['avatar'], 'skill-swap/avatars');
        
        if (!$result) Response::serverError('Failed to upload avatar');
        
        if (User::update($currentUser->id, ['avatar' => $result['url']])) {
            Response::success(['avatar_url' => $result['url']], 'Avatar uploaded successfully');
        } else {
            Response::serverError('Failed to save avatar');
        }
    }
}
