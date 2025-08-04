<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\ResponsiveImageController;

// USERSTUFF: Gonna need this later
// Route::get('/user', action: function (Request $request): User {
//     return $request->user();
// })->middleware('auth:sanctum');

// USERSTUFF: Define endpoints using rate-limiter for now (will be replaced by user-auth later)
Route::post('/v1/generate', [ResponsiveImageController::class, 'generate'])->middleware('throttle:generator');
Route::get('/download', [ResponsiveImageController::class, 'download'])->middleware('throttle:generator');

Route::middleware('auth:sanctum')->group(callback: function (): void {
    // USERSTUFF: Need this later when we add user stuff, quotas etc.
    // Putting the routes in here will require a valid user-session
});


