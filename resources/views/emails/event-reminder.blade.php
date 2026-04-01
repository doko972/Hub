<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappel de rendez-vous</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .card { background: #ffffff; border-radius: 12px; max-width: 520px; margin: 0 auto; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 32px 32px 24px; color: white; }
        .header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 600; }
        .header p { margin: 0; opacity: 0.85; font-size: 14px; }
        .body { padding: 28px 32px; }
        .event-title { font-size: 20px; font-weight: 700; color: #1a1a1a; margin: 0 0 16px; }
        .detail { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; color: #444; font-size: 15px; }
        .detail .icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .footer { background: #f9f9f9; border-top: 1px solid #eee; padding: 16px 32px; text-align: center; font-size: 13px; color: #888; }
        .badge { display: inline-block; background: #ede9fe; color: #7c3aed; border-radius: 6px; padding: 4px 10px; font-size: 13px; font-weight: 500; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>⏰ Rappel de rendez-vous</h1>
            <p>HR Chatbot vous rappelle votre prochain événement</p>
        </div>
        <div class="body">
            <div class="badge">📅 Google Calendar</div>
            <div class="event-title">{{ $reminder->event_title }}</div>

            <div class="detail">
                <span class="icon">🗓️</span>
                <span>
                    {{ $reminder->event_start->setTimezone('Europe/Paris')->translatedFormat('l j F Y') }}
                    à {{ $reminder->event_start->setTimezone('Europe/Paris')->format('H\hi') }}
                </span>
            </div>

            @if($reminder->event_location)
            <div class="detail">
                <span class="icon">📍</span>
                <span>{{ $reminder->event_location }}</span>
            </div>
            @endif

            <div class="detail">
                <span class="icon">⏱️</span>
                <span>Dans {{ $reminder->event_start->diffForHumans(now(), true) }}</span>
            </div>
        </div>
        <div class="footer">
            Envoyé par <strong>HR Chatbot</strong> · Vous recevez ce message car vous avez configuré un rappel via le chatbot.
        </div>
    </div>
</body>
</html>
