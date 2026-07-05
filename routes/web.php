<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

// Specific Laravel routes
Route::get('/admin', [AdminController::class, 'index']);
Route::get('/admin_dashboard.php', [AdminController::class, 'index']);

// Catch-all route for Legacy Bridge
Route::any('{any?}', function ($any = 'index.php') {
    if (empty($any)) {
        $any = 'index.php';
    }
    
    $path = base_path('legacy/' . $any);
    
    // Support routes without .php extension (e.g., /students -> /students.php)
    if (!str_ends_with($any, '.php') && file_exists(base_path('legacy/' . $any . '.php'))) {
        $path = base_path('legacy/' . $any . '.php');
    }
    
    if (file_exists($path) && is_file($path)) {
        // Change working directory so relative includes in legacy files work
        chdir(base_path('legacy'));
        
        // Start native session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Custom error handler to prevent Laravel from turning PHP notices/warnings into Exceptions
        set_error_handler(function ($severity, $message, $file, $line) {
            if (error_reporting() & $severity) {
                // Ignore session_start warning or standard PHP notices/warnings
                if ($severity === E_NOTICE || $severity === E_WARNING || $severity === E_DEPRECATED) {
                    return true;
                }
            }
            return false; // Fallback to default handler for real fatal errors
        });
        
        include $path;
        exit;
    }
    
    abort(404);
})->where('any', '.*');
