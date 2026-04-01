<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résumé de vos rendez-vous</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .card { background: #ffffff; border-radius: 12px; max-width: 560px; margin: 0 auto; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 32px 32px 24px; color: white; }
        .header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 600; }
        .header p { margin: 0; opacity: 0.85; font-size: 14px; }
        .body { padding: 24px 32px; }
        .greeting { font-size: 15px; color: #444; margin-bottom: 20px; }
        .empty { text-align: center; color: #888; font-size: 15px; padding: 24px 0; }
        .event { border: 1px solid #eee; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; background: #fafafa; }
        .event-title { font-size: 15px; font-weight: 600; color: #1a1a1a; margin-bottom: 6px; }
        .event-detail { font-size: 13px; color: #666; display: flex; align-items: center; gap: 6px; margin-top: 4px; }
        .badge { display: inline-block; background: #ede9fe; color: #7c3aed; border-radius: 6px; padding: 3px 9px; font-size: 12px; font-weight: 500; margin-bottom: 18px; }
        .footer { background: #f9f9f9; border-top: 1px solid #eee; padding: 16px 32px; text-align: center; font-size: 13px; color: #888; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>📅 Vos rendez-vous</h1>
            <p>{{ $periodLabel }}</p>
        </div>
        <div class="body">
            <p class="greeting">Bonjour <strong>{{ $user->name }}</strong>,</p>
            <div class="badge">{{ count($events) }} événement{{ count($events) > 1 ? 's' : '' }}</div>

            @forelse($events as $event)
                <div class="event">
                    <div class="event-title">{{ $event['summary'] ?? '(Sans titre)' }}</div>

                    @if(!empty($event['start']['dateTime']))
                        @php
                            $start = new \DateTime($event['start']['dateTime']);
                            $end   = new \DateTime($event['end']['dateTime']);
                        @endphp
                        <div class="event-detail">
                            🗓️ {{ $start->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y') }}
                            · {{ $start->format('H\hi') }} → {{ $end->format('H\hi') }}
                        </div>
                    @elseif(!empty($event['start']['date']))
                        <div class="event-detail">
                            🗓️ {{ \Carbon\Carbon::parse($event['start']['date'])->format('d/m/Y') }} (journée entière)
                        </div>
                    @endif

                    @if(!empty($event['location']))
                        <div class="event-detail">📍 {{ $event['location'] }}</div>
                    @endif

                    @if(!empty($event['description']))
                        <div class="event-detail">📝 {{ Str::limit($event['description'], 80) }}</div>
                    @endif
                </div>
            @empty
                <div class="empty">Aucun événement pour cette période.</div>
            @endforelse
        </div>
        <div class="footer">
            Envoyé par <strong>HR Chatbot</strong> · Résumé {{ $periodLabel }}
        </div>
    </div>
</body>
</html>
