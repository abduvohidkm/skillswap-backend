<?php

namespace SkillSwap\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;

class CloudinaryService {
    private static ?Cloudinary $cloudinary = null;
    
    /**
     * Initialize Cloudinary instance
     */
    private static function init(): void {
        if (self::$cloudinary === null) {
            self::$cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                    'api_key' => $_ENV['CLOUDINARY_API_KEY'],
                    'api_secret' => $_ENV['CLOUDINARY_API_SECRET']
                ]
            ]);
        }
    }
    
    /**
     * Upload image to Cloudinary
     */
    public static function uploadImage(array $file, string $folder = 'skill-swap'): ?array {
        try {
            self::init();
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new \Exception('Invalid image type. Allowed: JPG, PNG, GIF, WEBP');
            }
            
            // Validate file size (max 10MB)
            $maxSize = (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 10485760);
            if ($file['size'] > $maxSize) {
                throw new \Exception('File size exceeds maximum allowed size');
            }
            
            // Upload to Cloudinary
            $uploadApi = new UploadApi();
            $result = $uploadApi->upload($file['tmp_name'], [
                'folder' => $folder,
                'resource_type' => 'image',
                'transformation' => [
                    'quality' => 'auto',
                    'fetch_format' => 'auto'
                ]
            ]);
            
            return [
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'width' => $result['width'],
                'height' => $result['height'],
                'format' => $result['format']
            ];
            
        } catch (\Exception $e) {
            error_log("Cloudinary upload failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Upload document to Cloudinary
     */
    public static function uploadDocument(array $file, string $folder = 'skill-swap/documents'): ?array {
        try {
            self::init();
            
            // Validate file type
            $allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ];
            
            if (!in_array($file['type'], $allowedTypes)) {
                throw new \Exception('Invalid document type. Allowed: PDF, DOC, DOCX, PPT, PPTX');
            }
            
            // Validate file size (max 10MB)
            $maxSize = (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 10485760);
            if ($file['size'] > $maxSize) {
                throw new \Exception('File size exceeds maximum allowed size');
            }
            
            // Upload to Cloudinary
            $uploadApi = new UploadApi();
            $result = $uploadApi->upload($file['tmp_name'], [
                'folder' => $folder,
                'resource_type' => 'raw'
            ]);
            
            return [
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'format' => $result['format'],
                'size' => $result['bytes']
            ];
            
        } catch (\Exception $e) {
            error_log("Cloudinary upload failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete file from Cloudinary
     */
    public static function deleteFile(string $publicId): bool {
        try {
            self::init();
            $uploadApi = new UploadApi();
            $uploadApi->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            error_log("Cloudinary delete failed: " . $e->getMessage());
            return false;
        }
    }
}
