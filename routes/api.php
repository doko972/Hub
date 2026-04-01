<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\UpdateController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\SystemPromptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques (sans authentification)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Routes protégées (authentification requise)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Authentification
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Conversations
    Route::apiResource('conversations', ConversationController::class);

    // Messages (imbriqués dans conversations)
    Route::apiResource('conversations.messages', MessageController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    // Recherche web
    Route::get('/search', [SearchController::class, 'search']);

    // Upload et extraction de documents
    Route::post('/documents/extract', [DocumentController::class, 'extract']);

    // Chat IA (limité à 20 req/min/utilisateur)
    Route::post('/conversations/{conversation}/chat', [ChatController::class, 'chat'])
        ->middleware('throttle:chat');

    // Dossiers
    Route::get('/folders', [App\Http\Controllers\Api\FolderController::class, 'index']);
    Route::post('/folders', [App\Http\Controllers\Api\FolderController::class, 'store']);
    Route::put('/folders/{folder}', [App\Http\Controllers\Api\FolderController::class, 'update']);
    Route::delete('/folders/{folder}', [App\Http\Controllers\Api\FolderController::class, 'destroy']);

    // Déplacer une conversation dans un dossier
    Route::put('/conversations/{conversation}/move', [App\Http\Controllers\Api\ConversationController::class, 'move']);
    // Chat streaming (limité à 20 req/min/utilisateur)
    Route::post('/conversations/{conversation}/chat-stream', [App\Http\Controllers\Api\ChatController::class, 'chatStream'])
        ->middleware('throttle:chat');
    // Titre IA et régénération
    Route::post('/conversations/{conversation}/generate-title', [ChatController::class, 'generateTitle'])
        ->middleware('throttle:chat');
    Route::post('/conversations/{conversation}/regenerate', [ChatController::class, 'regenerate'])
        ->middleware('throttle:chat');
    // Édition de message
    Route::patch('/conversations/{conversation}/messages/{message}', [ChatController::class, 'editMessage']);
    // Partage de conversation
    Route::post('/conversations/{conversation}/share', [ChatController::class, 'share']);
    Route::delete('/conversations/{conversation}/share', [ChatController::class, 'unshare']);
    // Génération d'images
    Route::post('/conversations/{conversation}/imagine', [App\Http\Controllers\Api\ImageController::class, 'generate']);
    Route::post('/image/proxy', [ImageController::class, 'proxy']);

    Route::get('/google/status', [App\Http\Controllers\GoogleAuthController::class, 'status']);
    Route::post('/google/disconnect', [App\Http\Controllers\GoogleAuthController::class, 'disconnect']);

    // Notifications push
    Route::get('/push/vapid-key', [PushSubscriptionController::class, 'vapidKey']);
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);

    // Personnalités (system prompts)
    Route::apiResource('system-prompts', SystemPromptController::class)->except(['show']);
    Route::patch('/system-prompts/{systemPrompt}/set-default', [SystemPromptController::class, 'setDefault']);
});
Route::prefix('update')->group(function () {
    Route::get('/check', [UpdateController::class, 'check']);
    Route::get('/download/{release}', [UpdateController::class, 'download']);
});
