<?php

namespace App\Console\Commands;

use App\Mail\EventReminderMail;
use App\Models\EmailReminder;
use App\Services\PushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature   = 'reminders:send';
    protected $description = 'Envoie les rappels de rendez-vous Google Calendar par email et notification push';

    public function handle(): void
    {
        $due = EmailReminder::whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->with('user')
            ->get();

        $push = new PushService();

        foreach ($due as $reminder) {
            try {
                // Email — toujours vers l'utilisateur, BCC vers l'admin si configuré
                $recipient    = $reminder->user->email;
                $contactEmail = env('CONTACT_EMAIL');
                $mailer = Mail::to($recipient);
                if ($contactEmail && $contactEmail !== $recipient) {
                    $mailer = $mailer->bcc($contactEmail);
                }
                $mailer->send(new EventReminderMail($reminder));

                // Notification push navigateur
                $start = $reminder->event_start
                    ? \Carbon\Carbon::parse($reminder->event_start)
                        ->setTimezone('Europe/Paris')
                        ->format('d/m à H\hi')
                    : '';
                $body = $start ? "Prévu le {$start}" : 'Rendez-vous imminent';
                if ($reminder->event_location) {
                    $body .= " · 📍 {$reminder->event_location}";
                }

                $push->sendToUser(
                    $reminder->user_id,
                    "🔔 {$reminder->event_title}",
                    $body
                );

                $reminder->update(['sent_at' => now()]);

                $this->info("Rappel envoyé ({$recipient}) — {$reminder->event_title}");
            } catch (\Exception $e) {
                $this->error("Erreur rappel #{$reminder->id} : " . $e->getMessage());
            }
        }

        if ($due->isEmpty()) {
            $this->line('Aucun rappel à envoyer.');
        }
    }
}
