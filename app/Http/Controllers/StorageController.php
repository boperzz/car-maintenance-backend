<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StorageController extends Controller
{
    public function serve($path = null)
    {
        // If path is null or empty, try to get it from the request URI
        if (empty($path)) {
            $requestUri = request()->getRequestUri();
            // Extract path after /storage/
            if (preg_match('#/storage/(.+)$#', $requestUri, $matches)) {
                $path = $matches[1];
            } else {
                $requestPath = request()->path();
                // Remove 'storage/' prefix if present
                $path = str_replace('storage/', '', $requestPath);
            }
        }
        
        // Remove any leading slashes and decode URL encoding
        $filePath = ltrim($path, '/');
        $filePath = urldecode($filePath);
        
        // Build the full file path
        $fullPath = storage_path('app/public/' . $filePath);
        
        // Log the request for debugging
        Log::info('=== STORAGE ROUTE ACCESSED ===');
        Log::info('Path param: ' . ($path ?? 'null'));
        Log::info('File path: ' . $filePath);
        Log::info('Full path: ' . $fullPath);
        Log::info('Request URI: ' . request()->getRequestUri());
        Log::info('Request Path: ' . request()->path());
        Log::info('Request Method: ' . request()->method());
        
        // Check if file exists
        if (!file_exists($fullPath) || !is_file($fullPath)) {
            Log::warning('=== STORAGE FILE NOT FOUND ===');
            Log::warning('Path param: ' . $path);
            Log::warning('File path: ' . $filePath);
            Log::warning('Full path: ' . $fullPath);
            
            // Check if directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                Log::error('Storage directory does not exist: ' . $directory);
            } else {
                Log::info('Storage directory exists: ' . $directory);
                // List files in directory for debugging
                $files = glob($directory . '/*');
                if ($files) {
                    Log::info('Files in directory (' . count($files) . '): ' . implode(', ', array_map('basename', array_slice($files, 0, 20))));
                } else {
                    Log::info('Directory is empty: ' . $directory);
                }
            }
            
            // Return a more informative 404 response
            return response()->json([
                'error' => 'File not found',
                'path' => $filePath,
                'full_path' => $fullPath,
                'directory_exists' => is_dir($directory),
                'files_in_directory' => $files ? array_map('basename', array_slice($files, 0, 10)) : [],
            ], 404);
        }
        
        // Get file contents and MIME type
        $file = file_get_contents($fullPath);
        $type = mime_content_type($fullPath);
        
        Log::info('Storage file served successfully: ' . $filePath . ' (Size: ' . filesize($fullPath) . ' bytes, Type: ' . $type . ')');
        
        // Return file with proper headers
        return response($file, 200)
            ->header('Content-Type', $type)
            ->header('Cache-Control', 'public, max-age=31536000')
            ->header('Content-Length', filesize($fullPath));
    }
}

