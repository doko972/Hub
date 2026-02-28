<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ToolController;
use App\Http\Controllers\Admin\ToolFamilyController;
use App\Http\Controllers\Admin\UserController;

// ---- Authentification ----
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- Dashboard & profil (auth requis) ----
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Préférences d'affichage
    Route::get('/preferences',  [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::post('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    // Profil utilisateur
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
});

// ---- Administration (auth + admin requis) ----
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Familles d'outils
        Route::resource('families', ToolFamilyController::class)->except(['show'])
            ->parameters(['families' => 'family']);
        Route::get('families/{family}', [ToolFamilyController::class, 'show'])->name('families.show');

        // Outils
        Route::resource('tools', ToolController::class)->except(['show']);
        Route::get('tools/{tool}', [ToolController::class, 'show'])->name('tools.show');

        // Utilisateurs
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    });
