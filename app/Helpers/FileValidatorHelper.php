<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class FileValidatorHelper
{
    /**
     * Validate an uploaded image file.
     *
     * @param  \Illuminate\Http\UploadedFile|null  $file
     * @param  array  $allowedExtensions
     * @param  int  $maxSizeKB
     * @param  string  $fieldName
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateImage(?UploadedFile $file, array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'], int $maxSizeKB = 2048, string $fieldName = 'image')
    {
        if (!$file) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $sizeKB = $file->getSize() / 1024;

        if (!in_array($extension, $allowedExtensions)) {
            throw ValidationException::withMessages([
                $fieldName => 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions),
            ]);
        }

        if ($sizeKB > $maxSizeKB) {
            throw ValidationException::withMessages([
                $fieldName => "File too large. Maximum allowed size is {$maxSizeKB} KB.",
            ]);
        }
    }

    /**
     * Validate an uploaded video file.
     *
     * @param  \Illuminate\Http\UploadedFile|null  $file
     * @param  array  $allowedExtensions
     * @param  int  $maxSizeKB
     * @param  string  $fieldName
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateVideo(?UploadedFile $file, array $allowedExtensions = ['mp4', 'avi', 'mov', 'wmv', 'mkv', 'flv', 'webm'], int $maxSizeKB = 10240, string $fieldName = 'video')
    {
        if (!$file) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $sizeKB = $file->getSize() / 1024;

        // Get MIME type for additional validation
        $mimeType = $file->getMimeType();

        // Common video MIME types
        $videoMimeTypes = [
            'video/mp4',
            'video/x-msvideo', // avi
            'video/quicktime', // mov
            'video/x-ms-wmv',  // wmv
            'video/x-matroska', // mkv
            'video/x-flv',     // flv
            'video/webm',      // webm
        ];

        // Validate extension
        if (!in_array($extension, $allowedExtensions)) {
            throw ValidationException::withMessages([
                $fieldName => 'Invalid video file type. Allowed types: ' . implode(', ', $allowedExtensions),
            ]);
        }

        // Validate MIME type if available
        if ($mimeType && !in_array($mimeType, $videoMimeTypes)) {
            throw ValidationException::withMessages([
                $fieldName => 'Invalid video format. Please upload a valid video file.',
            ]);
        }

        // Validate file size
        if ($sizeKB > $maxSizeKB) {
            throw ValidationException::withMessages([
                $fieldName => "Video file too large. Maximum allowed size is " . self::formatFileSize($maxSizeKB * 1024) . ".",
            ]);
        }

        // Optional: Check if file is actually a video (basic check)
        if (!str_starts_with($mimeType, 'video/')) {
            throw ValidationException::withMessages([
                $fieldName => 'The file must be a video. Detected MIME type: ' . $mimeType,
            ]);
        }
    }

    /**
     * Validate a general file upload.
     *
     * @param  \Illuminate\Http\UploadedFile|null  $file
     * @param  array  $allowedExtensions
     * @param  int  $maxSizeKB
     * @param  string  $fieldName
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateFile(?UploadedFile $file, array $allowedExtensions = ['pdf', 'doc', 'docx', 'txt'], int $maxSizeKB = 5120, string $fieldName = 'file')
    {
        if (!$file) {
            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $sizeKB = $file->getSize() / 1024;

        if (!in_array($extension, $allowedExtensions)) {
            throw ValidationException::withMessages([
                $fieldName => 'Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions),
            ]);
        }

        if ($sizeKB > $maxSizeKB) {
            throw ValidationException::withMessages([
                $fieldName => "File too large. Maximum allowed size is {$maxSizeKB} KB.",
            ]);
        }
    }

    /**
     * Format file size to human readable format.
     *
     * @param  int  $bytes
     * @param  int  $precision
     * @return string
     */
    private static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Validate multiple files (for batch uploads).
     *
     * @param  array|null  $files
     * @param  string  $type  'image', 'video', or 'file'
     * @param  array  $allowedExtensions
     * @param  int  $maxSizeKB
     * @param  string  $fieldName
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateMultipleFiles(?array $files, string $type = 'image', array $allowedExtensions = [], int $maxSizeKB = 2048, string $fieldName = 'files')
    {
        if (!$files) {
            return;
        }

        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            switch ($type) {
                case 'video':
                    self::validateVideo($file, $allowedExtensions ?: ['mp4', 'avi', 'mov', 'wmv', 'mkv', 'flv', 'webm'], $maxSizeKB, "{$fieldName}.{$index}");
                    break;
                case 'file':
                    self::validateFile($file, $allowedExtensions ?: ['pdf', 'doc', 'docx', 'txt'], $maxSizeKB, "{$fieldName}.{$index}");
                    break;
                case 'image':
                default:
                    self::validateImage($file, $allowedExtensions ?: ['jpg', 'jpeg', 'png', 'webp'], $maxSizeKB, "{$fieldName}.{$index}");
                    break;
            }
        }
    }

    /**
     * Get allowed video extensions as a string for display.
     *
     * @return string
     */
    public static function getAllowedVideoExtensionsString(): string
    {
        return implode(', ', ['mp4', 'avi', 'mov', 'wmv', 'mkv', 'flv', 'webm']);
    }

    /**
     * Get maximum video size in human readable format.
     *
     * @param  int  $maxSizeKB
     * @return string
     */
    public static function getMaxVideoSizeString(int $maxSizeKB = 10240): string
    {
        return self::formatFileSize($maxSizeKB * 1024);
    }
}
