<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Rappels de RDV individuels — vérification chaque minute
        $schedule->command('reminders:send')->everyMinute();

        // Résumé hebdomadaire — chaque lundi à 7h00
        $schedule->command('calendar:weekly-summary')->weeklyOn(1, '07:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
