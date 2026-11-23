<?php

use App\Http\Controllers\Api\V1\Admin\AuthorController;
use App\Http\Controllers\Api\V1\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Public\BlogController as PublicBlogController;
use App\Http\Controllers\Api\V1\Public\CategoryController as PublicCategoryController;
use Illuminate\Support\Facades\Route;

/*
| WiserLeap API v1  (all routes are prefixed with /api)
|
| Public:  website blog (published posts only), cached.
| Auth:    Sanctum cookie login for the admin panel.
| Admin:   everything the dashboard needs; requires a logged-in admin.
*/

Route::prefix('v1')->group(function () {

    // ---------- Public website ----------
    Route::middleware('throttle:public')->group(function () {
        Route::get('blogs', [PublicBlogController::class, 'index']);
        Route::get('blogs/{slug}', [PublicBlogController::class, 'show']);
        Route::get('categories', [PublicCategoryController::class, 'index']);
    });
    Route::post('blogs/{slug}/view', [PublicBlogController::class, 'view'])->middleware('throttle:views');

    // ---------- Auth ----------
    Route::prefix('auth')->group(function () {
        Route::get('registration-status', [AuthController::class, 'registrationStatus'])->middleware('throttle:public');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:password-reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('user', [AuthController::class, 'user']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // ---------- Admin panel ----------
    Route::prefix('admin')->middleware(['auth:sanctum', 'throttle:admin'])->group(function () {
        Route::get('dashboard', DashboardController::class);

        Route::get('blogs', [AdminBlogController::class, 'index']);
        Route::post('blogs', [AdminBlogController::class, 'store']);
        Route::get('blogs/{blog}', [AdminBlogController::class, 'show']);
        Route::put('blogs/{blog}', [AdminBlogController::class, 'update']);
        Route::patch('blogs/{blog}/status', [AdminBlogController::class, 'status']);
        Route::delete('blogs/{blog}', [AdminBlogController::class, 'destroy']);

        Route::apiResource('categories', AdminCategoryController::class)->except('show');
        Route::apiResource('authors', AuthorController::class)->except('show');
    });
});
