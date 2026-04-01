<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\ToolController;
use App\Http\Controllers\Admin\ToolFamilyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\CortexWebController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\SharedConversationController;
use App\Http\Controllers\Tools\BackgroundRemoverController;
use App\Http\Controllers\Tools\ImageConverterController;

// ---- Authentification ----
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- Réinitialisation du mot de passe ----
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

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

    // Credentials (identifiants par outil)
    Route::post('/credentials/{tool}',   [CredentialController::class, 'store'])->name('credentials.store');
    Route::delete('/credentials/{tool}', [CredentialController::class, 'destroy'])->name('credentials.destroy');

    // Chatbot intégré (génère l'URL d'auto-login signée)
    Route::get('/chatbot/url', [ChatbotController::class, 'getUrl'])->name('chatbot.url');

    // Tools
    Route::get('/tools/background-remover',  [BackgroundRemoverController::class, 'index'])->name('tools.background-remover');
    Route::post('/tools/background-remover', [BackgroundRemoverController::class, 'remove'])->name('tools.background-remover.remove');
    Route::get('/tools/image-converter',     [ImageConverterController::class, 'index'])->name('tools.image-converter');

    // Chatbot / Cortex IA
    Route::get('/chat',             [CortexWebController::class, 'index'])->name('cortex.chat');
    Route::get('/chat/c/{conversation}', [CortexWebController::class, 'show'])->name('cortex.conversation');
});

// ---- Conversation partagée (publique) ----
Route::get('/share/{token}', [SharedConversationController::class, 'show'])->name('share.conversation');

// ---- Google OAuth ----
Route::get('/auth/google',          [GoogleAuthController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

// ---- Administration (auth + admin requis) ----
Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Familles d'outils
        Route::post('families/reorder', [ToolFamilyController::class, 'reorder'])->name('families.reorder');
        Route::resource('families', ToolFamilyController::class)->except(['show'])
            ->parameters(['families' => 'family']);
        Route::get('families/{family}', [ToolFamilyController::class, 'show'])->name('families.show');

        // Outils
        Route::post('tools/reorder', [ToolController::class, 'reorder'])->name('tools.reorder');
        Route::resource('tools', ToolController::class)->except(['show']);
        Route::get('tools/{tool}', [ToolController::class, 'show'])->name('tools.show');

        // Utilisateurs
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

        // Assignation en masse
        Route::get('assignments',  [AssignmentController::class, 'index'])->name('assignments.index');
        Route::post('assignments', [AssignmentController::class, 'update'])->name('assignments.update');

        // Journaux d'activité
        Route::get('logs', [ActivityLogController::class, 'index'])->name('logs.index');
    });
