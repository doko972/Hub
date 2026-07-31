<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tâches planifiées
|--------------------------------------------------------------------------
|
| Depuis Laravel 11, app/Console/Kernel.php n'est plus chargé : la
| planification doit être déclarée ici (ou dans bootstrap/app.php).
| Rappel : le cron système doit exécuter « php artisan schedule:run »
| chaque minute pour que ces tâches se déclenchent.
*/

// Rappels de RDV individuels — vérification chaque minute
Schedule::command('reminders:send')
    ->everyMinute()
    ->withoutOverlapping();

// Résumé hebdomadaire — chaque lundi à 7h00
Schedule::command('calendar:weekly-summary')
    ->weeklyOn(1, '07:00');

// Purge des jetons Sanctum expirés depuis plus de 24 h
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
