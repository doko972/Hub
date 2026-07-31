<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.hub');

        // Toutes les URL générées (liens de partage, redirections OAuth, assets)
        // passent en https hors développement. Sans cela, derrière un proxy TLS,
        // Laravel génère des URL http qui déclenchent des avertissements de
        // contenu mixte et exposent les jetons en clair sur le premier saut.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('chat', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Limiteur global des routes /api/* (activé via throttleApi()).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Authentification : volontairement bas et indexé sur l'IP, pour
        // ralentir le bruteforce sans dépendre de l'identifiant essayé.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
