<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\ResponsiveImageController;

// Route::get('/user', action: function (Request $request): User {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(callback: function (): void {
    Route::post('/v1/generate', [ResponsiveImageController::class, 'generate']);
});

Route::get('/download', [ResponsiveImageController::class, 'download'])->name('download');