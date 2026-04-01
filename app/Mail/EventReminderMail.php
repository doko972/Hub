<?php

namespace App\Mail;

use App\Models\EmailReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmailReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⏰ Rappel : ' . $this->reminder->event_title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.event-reminder',
        );
    }
}
