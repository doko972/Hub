<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CalendarSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $user,
        public array  $events,
        public string $periodLabel,
        public string $periodStart,
        public string $periodEnd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📅 Vos rendez-vous — ' . $this->periodLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.calendar-summary',
        );
    }
}
