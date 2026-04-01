<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class GoogleCalendarService
{
    private User $user;
    private string $baseUrl = 'https://www.googleapis.com/calendar/v3';

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function isConnected(): bool
    {
        return !empty($this->user->google_access_token);
    }

    /**
     * Rafraîchit le token si expiré ou sur le point d'expirer
     */
    private function ensureValidToken(): void
    {
        if (!$this->isConnected()) {
            throw new \Exception('Google Calendar non connecté.');
        }

        if ($this->user->google_token_expires_at && now()->addMinutes(5)->gt($this->user->google_token_expires_at)) {
            $this->refreshToken();
        }
    }

    private function refreshToken(): void
    {
        if (!$this->user->google_refresh_token) {
            throw new \Exception('Session Google expirée. Veuillez reconnecter Google Calendar.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $this->user->google_refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Impossible de renouveler l\'accès Google Calendar.');
        }

        $data = $response->json();
        $this->user->update([
            'google_access_token'    => $data['access_token'],
            'google_token_expires_at' => now()->addSeconds($data['expires_in']),
        ]);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->user->google_access_token];
    }

    /**
     * Lister les événements entre deux dates
     */
    public function getEvents(string $timeMin, string $timeMax, int $maxResults = 15): array
    {
        $this->ensureValidToken();

        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/calendars/primary/events", [
                'timeMin'      => $timeMin,
                'timeMax'      => $timeMax,
                'maxResults'   => $maxResults,
                'orderBy'      => 'startTime',
                'singleEvents' => 'true',
                'timeZone'     => 'Europe/Paris',
            ]);

        if (!$response->successful()) {
            throw new \Exception('Erreur lors de la récupération des événements.');
        }

        return $response->json()['items'] ?? [];
    }

    /**
     * Créer un événement
     */
    private const COLOR_MAP = [
        'lavande'   => '1',
        'sauge'     => '2',
        'raisin'    => '3',
        'flamant'   => '4',
        'banane'    => '5',
        'mandarine' => '6',
        'paon'      => '7',
        'graphite'  => '8',
        'myrtille'  => '9',
        'basilic'   => '10',
        'tomate'    => '11',
    ];

    public function createEvent(string $title, string $start, string $end, ?string $description = null, ?string $location = null, ?string $color = null, ?int $reminderMinutes = null): array
    {
        $this->ensureValidToken();

        $body = [
            'summary' => $title,
            'start'   => ['dateTime' => $start, 'timeZone' => 'Europe/Paris'],
            'end'     => ['dateTime' => $end,   'timeZone' => 'Europe/Paris'],
        ];

        if ($description) $body['description'] = $description;
        if ($location)    $body['location']    = $location;
        if ($color && isset(self::COLOR_MAP[$color])) {
            $body['colorId'] = self::COLOR_MAP[$color];
        }
        if ($reminderMinutes) {
            $body['reminders'] = [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'popup', 'minutes' => $reminderMinutes],
                    ['method' => 'email', 'minutes' => $reminderMinutes],
                ],
            ];
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/calendars/primary/events", $body);

        if (!$response->successful()) {
            throw new \Exception('Erreur lors de la création de l\'événement : ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Modifier un événement existant
     */
    public function updateEvent(string $eventId, array $updates): array
    {
        $this->ensureValidToken();

        // Normaliser les dates si présentes
        foreach (['start', 'end'] as $field) {
            if (isset($updates[$field]) && is_string($updates[$field])) {
                $updates[$field] = ['dateTime' => $updates[$field], 'timeZone' => 'Europe/Paris'];
            }
        }

        $response = Http::withHeaders($this->headers())
            ->patch("{$this->baseUrl}/calendars/primary/events/{$eventId}", $updates);

        if (!$response->successful()) {
            throw new \Exception('Erreur lors de la modification de l\'événement.');
        }

        return $response->json();
    }

    /**
     * Supprimer un événement
     */
    public function deleteEvent(string $eventId): void
    {
        $this->ensureValidToken();

        $response = Http::withHeaders($this->headers())
            ->delete("{$this->baseUrl}/calendars/primary/events/{$eventId}");

        if ($response->status() !== 204 && !$response->successful()) {
            throw new \Exception('Erreur lors de la suppression de l\'événement.');
        }
    }

    /**
     * Formater une liste d'événements en Markdown lisible
     */
    public static function formatEventsList(array $events, string $period = '', bool $showIds = true): string
    {
        if (empty($events)) {
            return "Aucun événement trouvé" . ($period ? " pour {$period}" : '') . ".";
        }

        $label = $period ? "**📅 Événements {$period} :**" : "**📅 Vos événements :**";
        $lines = [$label, ''];

        foreach ($events as $i => $event) {
            $num   = $i + 1;
            $title = $event['summary'] ?? '(Sans titre)';
            $id    = $event['id'] ?? '';

            // Formater les dates
            if (!empty($event['start']['dateTime'])) {
                $start = new \DateTime($event['start']['dateTime']);
                $end   = new \DateTime($event['end']['dateTime']);
                $dateStr = self::frenchDate($start) . ', ' . $start->format('H\hi') . '–' . $end->format('H\hi');
            } elseif (!empty($event['start']['date'])) {
                $start   = new \DateTime($event['start']['date']);
                $dateStr = self::frenchDate($start) . ' (journée entière)';
            } else {
                $dateStr = 'Date inconnue';
            }

            $lines[] = "{$num}. **{$title}** — {$dateStr}";

            if (!empty($event['location'])) {
                $lines[] = "   📍 {$event['location']}";
            }
            if ($id && $showIds) {
                $lines[] = "   *(ID: `{$id}`)*";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Formater un événement créé en Markdown
     */
    public static function formatCreatedEvent(array $event, ?int $reminderMinutes = null): string
    {
        $title = $event['summary'] ?? '(Sans titre)';
        $id    = $event['id'] ?? '';
        $link  = $event['htmlLink'] ?? '';

        $lines = ["✅ **Événement créé avec succès !**", ''];
        $lines[] = "📅 **{$title}**";

        if (!empty($event['start']['dateTime'])) {
            $start = new \DateTime($event['start']['dateTime']);
            $end   = new \DateTime($event['end']['dateTime']);
            $lines[] = "🗓️ " . self::frenchDate($start) . ' · ' . $start->format('H\hi') . ' → ' . $end->format('H\hi');
        }

        if (!empty($event['location'])) {
            $lines[] = "📍 {$event['location']}";
        }
        if ($reminderMinutes) {
            $lines[] = "⏰ Rappel configuré : **{$reminderMinutes} min avant** (Google Calendar + email)";
        }
        if ($id) {
            $lines[] = "*(ID: `{$id}`)*";
        }
        if ($link) {
            $lines[] = '';
            $lines[] = "[Voir dans Google Calendar]({$link})";
        }

        return implode("\n", $lines);
    }

    private static function frenchDate(\DateTime $dt): string
    {
        $days   = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
        $months = ['jan.', 'fév.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sep.', 'oct.', 'nov.', 'déc.'];
        return $days[(int)$dt->format('w')] . ' ' . $dt->format('j') . ' ' . $months[(int)$dt->format('n') - 1];
    }
}
