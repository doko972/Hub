<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\PushSubscriptionController;
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
use App\Http\Controllers\Tools\QrCodeController;

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

    // Thème d'interface
    Route::get('/preferences/theme',  [PreferencesController::class, 'theme'])->name('preferences.theme');
    Route::post('/preferences/theme', [PreferencesController::class, 'updateTheme'])->name('preferences.theme.update');

    // Profil utilisateur
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

    // ---- Messagerie interne ----
    // Les routes statiques précèdent /{discussion}, sinon « unread » ou
    // « groups » seraient interprétés comme des identifiants de fil.
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/',        [DiscussionController::class, 'index'])->name('index');
        Route::get('/unread',  [DiscussionController::class, 'unread'])->name('unread');
        Route::post('/groups', [DiscussionController::class, 'storeGroup'])->name('groups.store');
        Route::post('/direct/{user}', [DiscussionController::class, 'openDirect'])->name('direct');
        Route::get('/attachments/{attachment}', [DiscussionController::class, 'attachment'])->name('attachment');

        Route::get('/{discussion}',              [DiscussionController::class, 'index'])->name('show');
        Route::get('/{discussion}/poll',         [DiscussionController::class, 'poll'])->name('poll');
        Route::post('/{discussion}/leave',       [DiscussionController::class, 'leave'])->name('leave');
        Route::post('/{discussion}/participants',[DiscussionController::class, 'addParticipants'])->name('participants.add');

        Route::patch('/{discussion}/messages/{message}', [DiscussionController::class, 'updateMessage'])
            ->middleware('throttle:60,1')
            ->name('messages.update');

        Route::delete('/{discussion}/messages/{message}', [DiscussionController::class, 'destroyMessage'])
            ->middleware('throttle:60,1')
            ->name('messages.destroy');

        Route::post('/{discussion}/messages/{message}/reactions', [DiscussionController::class, 'toggleReaction'])
            ->middleware('throttle:120,1')
            ->name('reactions.toggle');

        Route::get('/gifs/search', [DiscussionController::class, 'searchGifs'])
            ->middleware('throttle:60,1')
            ->name('gifs.search');

        Route::post('/{discussion}/gif', [DiscussionController::class, 'sendGif'])
            ->middleware('throttle:30,1')
            ->name('gif.send');

        Route::post('/{discussion}', [DiscussionController::class, 'send'])
            ->middleware('throttle:60,1')
            ->name('send');
    });

    // Notifications push — mêmes actions que /api/push/*, mais authentifiées
    // par session : les pages du Hub n'ont pas de jeton Sanctum.
    Route::prefix('push')->name('push.')->group(function () {
        Route::get('/vapid-key',   [PushSubscriptionController::class, 'vapidKey'])->name('vapid');
        Route::post('/subscribe',  [PushSubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::post('/unsubscribe',[PushSubscriptionController::class, 'unsubscribe'])->name('unsubscribe');
    });

    // Présence (sondage de la sidebar : ~2 requêtes/min/utilisateur)
    Route::get('/presence', [PresenceController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('presence.index');

    // Credentials (identifiants par outil)
    Route::get('/credentials/{tool}',    [CredentialController::class, 'show'])->name('credentials.show');
    Route::post('/credentials/{tool}',   [CredentialController::class, 'store'])->name('credentials.store');
    Route::delete('/credentials/{tool}', [CredentialController::class, 'destroy'])->name('credentials.destroy');

    // Chatbot intégré (génère l'URL d'auto-login signée)
    Route::get('/chatbot/url', [ChatbotController::class, 'getUrl'])->name('chatbot.url');

    // Tools
    Route::get('/tools/background-remover',  [BackgroundRemoverController::class, 'index'])->name('tools.background-remover');
    Route::post('/tools/background-remover', [BackgroundRemoverController::class, 'remove'])->name('tools.background-remover.remove');
    Route::get('/tools/image-converter',     [ImageConverterController::class, 'index'])->name('tools.image-converter');
    Route::get('/tools/qr-code',             [QrCodeController::class, 'index'])->name('tools.qr-code');

    // Chatbot / Cortex IA
    Route::get('/chat',             [CortexWebController::class, 'index'])->name('cortex.chat');
    Route::get('/chat/c/{conversation}', [CortexWebController::class, 'show'])->name('cortex.conversation');

    // Google OAuth — sous 'auth' : le compte Google est rattaché à l'utilisateur
    // de la session, jamais à un identifiant fourni dans l'URL.
    Route::get('/auth/google',          [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

// ---- Conversation partagée (publique) ----
Route::get('/share/{token}', [SharedConversationController::class, 'show'])->name('share.conversation');

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
