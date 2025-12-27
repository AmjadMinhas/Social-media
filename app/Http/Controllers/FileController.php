<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    public function show($filename)
    {
        $path = storage_path('app/' . $filename);

        if (!file_exists($path)) {
            abort(404);
        }

        $file = \File::get($path);
        $type = \File::mimeType($path);

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
