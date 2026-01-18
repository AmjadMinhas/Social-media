<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    public function show($filename)
    {
        $path = storage_path('app/' . $filename);
        
        \Illuminate\Support\Facades\Log::info('FileController: File access request', [
            'filename' => $filename,
            'full_path' => $path,
            'file_exists' => file_exists($path),
            'is_readable' => file_exists($path) ? is_readable($path) : false,
            'file_size' => file_exists($path) ? filesize($path) : null,
            'request_url' => request()->fullUrl(),
            'user_agent' => request()->userAgent(),
            'ip' => request()->ip(),
        ]);

        if (!file_exists($path)) {
            \Illuminate\Support\Facades\Log::warning('FileController: File not found', [
                'filename' => $filename,
                'full_path' => $path,
                'storage_app_exists' => is_dir(storage_path('app')),
                'storage_app_readable' => is_dir(storage_path('app')) ? is_readable(storage_path('app')) : false,
                'directory_listing' => is_dir(dirname($path)) ? array_slice(scandir(dirname($path)), 0, 10) : null,
            ]);
            abort(404);
        }

        $file = \File::get($path);
        $type = \File::mimeType($path);
        
        \Illuminate\Support\Facades\Log::info('FileController: File served successfully', [
            'filename' => $filename,
            'file_size' => strlen($file),
            'mime_type' => $type,
        ]);

        $response = \Response::make($file, 200);
        $response->header("Content-Type", $type);
        
        // Add headers to allow Instagram/Facebook to fetch the image
        // This is especially important for ngrok URLs
        $response->header("Access-Control-Allow-Origin", "*");
        $response->header("Access-Control-Allow-Methods", "GET, HEAD, OPTIONS");
        $response->header("Access-Control-Allow-Headers", "*");
        
        // Cache headers to help with external access
        $response->header("Cache-Control", "public, max-age=3600");
        
        // Disable ngrok browser warning for API requests
        // This helps when Instagram/Facebook servers try to fetch the image
        $response->header("ngrok-skip-browser-warning", "true");

        return $response;
    }
}
