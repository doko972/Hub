<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Depuis Laravel 11, le groupe 'api' n'applique plus de limitation de
        // débit par défaut : sans cet appel, toutes les routes /api/* sont
        // ouvertes en illimité (bruteforce du login, abus des appels IA…).
        $middleware->throttleApi();

        // En-têtes de sécurité sur toutes les réponses, y compris les erreurs.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // CSP à nonce : uniquement sur les réponses HTML (groupe web).
        $middleware->web(append: [
            \App\Http\Middleware\ContentSecurityPolicy::class,
            \App\Http\Middleware\TrackLastSeen::class,
        ]);

        // Les requêtes de l'application de bureau (jeton Sanctum) comptent
        // aussi comme une présence.
        $middleware->api(append: [
            \App\Http\Middleware\TrackLastSeen::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
