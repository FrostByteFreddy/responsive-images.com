<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ResponsiveImageController;

Route::get('/', function () {
    return view('index');
});

Route::get('/download', [ResponsiveImageController::class, 'download'])->name('download');
