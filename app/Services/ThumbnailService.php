<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

class ThumbnailService
{
    /**
     * Generate thumbnail for image or video
     */
    public static function generateThumbnail($file, $originalPath, $storageSystem = 'local')
    {
        try {
            $fileExtension = strtolower($file->getClientOriginalExtension());
            $mimeType = $file->getMimeType();
            
            // Check if it's a video
            $isVideo = in_array($fileExtension, ['mp4', 'mov', 'avi']) || 
                       str_starts_with($mimeType, 'video/');
            
            if ($isVideo) {
                return self::generateVideoThumbnail($file, $originalPath, $storageSystem);
            } else {
                // For images, return the original URL as thumbnail (they're already displayable)
                // You can generate a resized version if needed, but for simplicity we'll use the original
                return null; // Return null so frontend uses original image
            }
        } catch (\Exception $e) {
            Log::error('Thumbnail generation error: ' . $e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Generate thumbnail for video using FFmpeg
     */
    protected static function generateVideoThumbnail($file, $originalPath, $storageSystem)
    {
        try {
            // Check if FFmpeg is available
            $ffmpegConfig = config('ffmpeg.ffmpeg.binaries');
            if (!$ffmpegConfig || !file_exists($ffmpegConfig)) {
                Log::warning('FFmpeg not available, skipping video thumbnail generation');
                return null;
            }
            
            // Create thumbnail directory
            $thumbnailDir = dirname($originalPath) . '/thumbnails';
            
            // Generate thumbnail filename
            $fileName = basename($originalPath);
            $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
            $thumbnailFileName = $nameWithoutExt . '_thumb.jpg';
            $thumbnailPath = $thumbnailDir . '/' . $thumbnailFileName;
            
            // Get file path
            $filePath = $file->getRealPath();
            
            // Create FFmpeg instance
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => config('ffmpeg.ffmpeg.binaries'),
                'ffprobe.binaries' => config('ffmpeg.ffprobe.binaries'),
                'timeout' => config('ffmpeg.timeout', 3600),
            ]);
            
            // Open video
            $video = $ffmpeg->open($filePath);
            
            // Get video duration from format
            $duration = $video->getFormat()->get('duration');
            
            // Extract frame at 1 second or middle of video, whichever is smaller
            $frameTime = $duration ? min(1, $duration / 2) : 1;
            
            // Extract frame
            $frame = $video->frame(TimeCode::fromSeconds($frameTime));
            
            // Save thumbnail
            $tempThumbnailPath = sys_get_temp_dir() . '/' . uniqid() . '_thumb.jpg';
            $frame->save($tempThumbnailPath);
            
            if ($storageSystem === 'aws') {
                $thumbnailContent = file_get_contents($tempThumbnailPath);
                Storage::disk('s3')->put($thumbnailPath, $thumbnailContent, 'public');
                $thumbnailUrl = Storage::disk('s3')->url($thumbnailPath);
            } else {
                // Ensure directory exists
                $fullThumbnailPath = storage_path('app/' . $thumbnailPath);
                $thumbnailDirPath = dirname($fullThumbnailPath);
                if (!file_exists($thumbnailDirPath)) {
                    mkdir($thumbnailDirPath, 0755, true);
                }
                
                copy($tempThumbnailPath, $fullThumbnailPath);
                $thumbnailUrl = rtrim(config('app.url'), '/') . '/media/' . ltrim($thumbnailPath, '/');
            }
            
            // Clean up temp file
            @unlink($tempThumbnailPath);
            
            return $thumbnailUrl;
        } catch (\Exception $e) {
            Log::error('Video thumbnail generation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}

