<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| STATIC FILE SERVING (bypasses symlink issue on Windows/OneDrive)
|--------------------------------------------------------------------------
*/

// routes/web.php
Route::get('/storage/avatars/{filename}', function ($filename) {
    $filename = basename($filename);
    $path     = storage_path('app/public/avatars/' . $filename);

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type'  => mime_content_type($path),
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->withoutMiddleware(['web', \Illuminate\Session\Middleware\StartSession::class]);