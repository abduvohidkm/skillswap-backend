<?php

namespace SkillSwap\Utils;

/**
 * Image Upload Handler
 * Handles image uploads with validation, resizing, and compression
 */
class ImageUploadHandler {
    
    private const MAX_FILE_SIZE = 31457280; // 30MB in bytes
    private const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const TARGET_WIDTH = 800;
    private const TARGET_HEIGHT = 450;
    private const JPEG_QUALITY = 85;
    private const PNG_COMPRESSION = 8;
    
    /**
     * Upload and process image
     * 
     * @param array $file $_FILES array element
     * @param string $uploadDir Upload directory path
     * @return array ['success' => bool, 'url' => string|null, 'error' => string|null]
     */
    public static function upload(array $file, string $uploadDir = '../uploads/courses/'): array {
        // Validate file upload
        $validation = self::validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        try {
            // Generate unique filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('course_', true) . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            // Process and save image
            $processed = self::processImage($file['tmp_name'], $filepath, $extension);
            
            if (!$processed['success']) {
                return $processed;
            }
            
            // Generate URL
            $baseUrl = $_ENV['BASE_URL'] ?? 'https://skill-swap-backend-production.up.railway.app';
            $imageUrl = $baseUrl . '/uploads/courses/' . $filename;
            
            return [
                'success' => true,
                'url' => $imageUrl,
                'filename' => $filename,
                'size' => filesize($filepath),
                'dimensions' => $processed['dimensions'],
                'error' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'Failed to process image: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private static function validateFile(array $file): array {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'No file uploaded or upload error occurred'
            ];
        }
        
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'File size exceeds 30MB limit'
            ];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'Invalid file type. Only JPG and PNG images are allowed'
            ];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'Invalid file extension. Only .jpg, .jpeg, and .png are allowed'
            ];
        }
        
        return ['success' => true, 'url' => null, 'error' => null];
    }
    
    /**
     * Process image: resize and compress
     */
    private static function processImage(string $sourcePath, string $destPath, string $extension): array {
        // Load image based on type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = @imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $source = @imagecreatefrompng($sourcePath);
                break;
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported image type'
                ];
        }
        
        if (!$source) {
            return [
                'success' => false,
                'error' => 'Failed to load image'
            ];
        }
        
        // Get original dimensions
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);
        
        // Validate minimum dimensions
        if ($origWidth < 400 || $origHeight < 225) {
            imagedestroy($source);
            return [
                'success' => false,
                'error' => 'Image dimensions too small. Minimum 400x225px required'
            ];
        }
        
        // Calculate new dimensions (maintain aspect ratio)
        $dimensions = self::calculateDimensions($origWidth, $origHeight);
        
        // Create new image
        $resized = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
        
        // Preserve transparency for PNG
        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $dimensions['width'], $dimensions['height'], $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $resized, $source,
            0, 0, 0, 0,
            $dimensions['width'], $dimensions['height'],
            $origWidth, $origHeight
        );
        
        // Save image with compression
        $saved = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $saved = imagejpeg($resized, $destPath, self::JPEG_QUALITY);
                break;
            case 'png':
                $saved = imagepng($resized, $destPath, self::PNG_COMPRESSION);
                break;
        }
        
        // Free memory
        imagedestroy($source);
        imagedestroy($resized);
        
        if (!$saved) {
            return [
                'success' => false,
                'error' => 'Failed to save processed image'
            ];
        }
        
        return [
            'success' => true,
            'dimensions' => $dimensions
        ];
    }
    
    /**
     * Calculate new dimensions maintaining aspect ratio
     */
    private static function calculateDimensions(int $origWidth, int $origHeight): array {
        $targetWidth = self::TARGET_WIDTH;
        $targetHeight = self::TARGET_HEIGHT;
        
        // Calculate aspect ratios
        $origRatio = $origWidth / $origHeight;
        $targetRatio = $targetWidth / $targetHeight;
        
        if ($origRatio > $targetRatio) {
            // Original is wider
            $newWidth = $targetWidth;
            $newHeight = (int)($targetWidth / $origRatio);
        } else {
            // Original is taller
            $newHeight = $targetHeight;
            $newWidth = (int)($targetHeight * $origRatio);
        }
        
        return [
            'width' => $newWidth,
            'height' => $newHeight
        ];
    }
    
    /**
     * Delete uploaded image
     */
    public static function delete(string $filename, string $uploadDir = '../uploads/courses/'): bool {
        $filepath = $uploadDir . $filename;
        
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        
        return false;
    }
    
    /**
     * Get image info
     */
    public static function getImageInfo(string $filepath): ?array {
        if (!file_exists($filepath)) {
            return null;
        }
        
        $info = @getimagesize($filepath);
        if (!$info) {
            return null;
        }
        
        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
            'size' => filesize($filepath),
            'extension' => pathinfo($filepath, PATHINFO_EXTENSION)
        ];
    }
    
    /**
     * Validate image URL
     */
    public static function validateUrl(string $url): bool {
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Check if URL ends with image extension
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
        return in_array($extension, self::ALLOWED_EXTENSIONS);
    }
    
    /**
     * Download image from URL and save
     */
    public static function downloadFromUrl(string $url, string $uploadDir = '../uploads/courses/'): array {
        // Validate URL
        if (!self::validateUrl($url)) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'Invalid image URL'
            ];
        }
        
        try {
            // Download image
            $imageData = @file_get_contents($url);
            
            if ($imageData === false) {
                return [
                    'success' => false,
                    'url' => null,
                    'error' => 'Failed to download image from URL'
                ];
            }
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'img_');
            file_put_contents($tempFile, $imageData);
            
            // Process as uploaded file
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            
            $file = [
                'name' => basename($url),
                'tmp_name' => $tempFile,
                'size' => filesize($tempFile),
                'error' => UPLOAD_ERR_OK
            ];
            
            $result = self::upload($file, $uploadDir);
            
            // Clean up temp file
            @unlink($tempFile);
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'url' => null,
                'error' => 'Failed to download image: ' . $e->getMessage()
            ];
        }
    }
}
