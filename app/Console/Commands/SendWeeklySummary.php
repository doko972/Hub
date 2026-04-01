<?php

namespace App\Console\Commands;

use App\Mail\CalendarSummaryMail;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklySummary extends Command
{
    protected $signature   = 'calendar:weekly-summary';
    protected $description = 'Envoie un résumé hebdomadaire des RDV Google Calendar à tous les utilisateurs connectés';

    public function handle(): void
    {
        $monday = now('Europe/Paris')->startOfWeek();
        $sunday = now('Europe/Paris')->endOfWeek();

        $users = User::whereNotNull('google_access_token')->get();

        if ($users->isEmpty()) {
            $this->line('Aucun utilisateur avec Google Calendar connecté.');
            return;
        }

        foreach ($users as $user) {
            try {
                $calendar = new GoogleCalendarService($user);
                $events   = $calendar->getEvents(
                    $monday->toIso8601String(),
                    $sunday->toIso8601String(),
                    50
                );

                $periodLabel = 'Semaine du ' . $monday->format('d/m') . ' au ' . $sunday->format('d/m/Y');

                // Email — toujours vers l'utilisateur, BCC vers l'admin si configuré
                $contactEmail = env('CONTACT_EMAIL');
                $mailer = Mail::to($user->email);
                if ($contactEmail && $contactEmail !== $user->email) {
                    $mailer = $mailer->bcc($contactEmail);
                }
                $mailer->send(new CalendarSummaryMail(
                    $user,
                    $events,
                    $periodLabel,
                    $monday->toIso8601String(),
                    $sunday->toIso8601String(),
                ));

                $this->info("Résumé envoyé à {$user->email} ({$user->name}) — " . count($events) . ' événement(s)');
            } catch (\Exception $e) {
                $this->error("Erreur pour {$user->email} : " . $e->getMessage());
            }
        }
    }
}
