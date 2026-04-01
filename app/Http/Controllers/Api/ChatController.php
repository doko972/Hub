<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\EmailReminder;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMemory;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    /**
     * Liste des modèles disponibles
     */
    public static $models = [
        'gpt-5' => ['provider' => 'openai', 'name' => 'GPT-5'],
        'gpt-5-mini' => ['provider' => 'openai', 'name' => 'GPT-5 Mini'],
        'gpt-4o' => ['provider' => 'openai', 'name' => 'GPT-4o'],
        'gpt-4o-mini' => ['provider' => 'openai', 'name' => 'GPT-4o Mini'],
        'claude-sonnet-4-20250514' => ['provider' => 'anthropic', 'name' => 'Claude Sonnet 4'],
        'claude-haiku-3-5-20241022' => ['provider' => 'anthropic', 'name' => 'Claude Haiku 3.5'],
    ];

    /**
     * Envoyer un message et recevoir une réponse IA (non-streaming)
     */
    public function chat(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string',
            'system_prompt' => 'nullable|string|max:3000',
            'image' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        if (empty($validated['content']) && empty($validated['image'])) {
            return response()->json(['message' => 'Un message ou une image est requis.'], 422);
        }

        $messageContent = $validated['content'] ?? '';
        $imageData = $validated['image'] ?? null;
        $model = $validated['model'] ?? 'gpt-4o';

        // Sauvegarder l'image sur le disque si présente
        $imagePath = null;
        if ($imageData) {
            $imagePath = $this->saveImage($imageData, $request->user()->id);
        }

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $messageContent ?: '[Image]',
            'has_image' => $imagePath ? true : false,
            'image_path' => $imagePath,
        ]);

        $history = $this->truncateHistory(
            $conversation->messages()
                ->where('id', '!=', $userMessage->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
                ->toArray()
        );

        $systemPrompt = $validated['system_prompt'] ?? 'Tu es un assistant utile et amical. Tu réponds en français de manière concise et claire.';

        try {
            $provider = self::$models[$model]['provider'] ?? 'openai';

            if ($provider === 'anthropic') {
                $assistantContent = $this->callAnthropic($model, $systemPrompt, $history, $messageContent, $imageData);
            } else {
                $assistantContent = $this->callOpenAI($model, $systemPrompt, $history, $messageContent, $imageData);
            }

            $assistantMessage = $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $assistantContent,
            ]);

            $conversation->touch();

            return response()->json([
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la communication avec l\'IA',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envoyer un message et recevoir une réponse IA en streaming
     */
    public function chatStream(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string',
            'system_prompt' => 'nullable|string|max:3000',
            'image' => 'nullable|string',
            'model' => 'nullable|string',
        ]);

        if (empty($validated['content']) && empty($validated['image'])) {
            return response()->json(['message' => 'Un message ou une image est requis.'], 422);
        }

        $messageContent = $validated['content'] ?? '';
        $imageData = $validated['image'] ?? null;
        $model = $validated['model'] ?? 'gpt-4o';

        // Sauvegarder l'image sur le disque si présente
        $imagePath = null;
        if ($imageData) {
            $imagePath = $this->saveImage($imageData, $request->user()->id);
        }

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $messageContent ?: '[Image]',
            'has_image' => $imagePath ? true : false,
            'image_path' => $imagePath,
        ]);

        $history = $this->truncateHistory(
            $conversation->messages()
                ->where('id', '!=', $userMessage->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
                ->toArray()
        );

        $systemPrompt = $validated['system_prompt'] ?? 'Tu es un assistant utile et amical. Tu peux générer des images sur demande. Tu réponds en français de manière concise et claire.';

        $provider = self::$models[$model]['provider'] ?? 'openai';
        $user = $request->user();

        return response()->stream(function () use ($conversation, $userMessage, $history, $messageContent, $imageData, $systemPrompt, $model, $provider, $user) {
            $fullContent = '';
            $generatedImagePath = null;

            try {
                if ($provider === 'anthropic') {
                    $fullContent = $this->streamAnthropic($model, $systemPrompt, $history, $messageContent, $imageData);
                } else {
                    $result = $this->streamOpenAI($model, $systemPrompt, $history, $messageContent, $imageData, $user);
                    $fullContent = $result['text'];
                    $generatedImagePath = $result['image_path'];
                }

                $messageData = ['role' => 'assistant', 'content' => $fullContent];
                if ($generatedImagePath) {
                    $messageData['has_image'] = true;
                    $messageData['image_path'] = $generatedImagePath;
                }

                $assistantMessage = $conversation->messages()->create($messageData);

                $conversation->touch();

                echo "data: " . json_encode([
                    'done' => true,
                    'user_message' => $userMessage,
                    'assistant_message' => $assistantMessage,
                ]) . "\n\n";
                ob_flush();
                flush();
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Appel OpenAI (non-streaming)
     */
    private function callOpenAI($model, $systemPrompt, $history, $messageContent, $imageData)
    {
        $currentMessage = $this->buildOpenAIMessage($messageContent, $imageData);

        $isGpt5     = str_starts_with($model, 'gpt-5');
        $tokenParam = $isGpt5 ? 'max_completion_tokens' : 'max_tokens';

        $params = [
            'model'     => $model,
            'messages'  => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $history,
                [$currentMessage]
            ),
            $tokenParam => 2000,
        ];
        if (!$isGpt5) {
            $params['temperature'] = 0.7;
        }

        $response = OpenAI::chat()->create($params);

        return $response->choices[0]->message->content;
    }

    /**
     * Streaming OpenAI — tool calling : génération d'images + Google Calendar
     * Retourne ['text' => string, 'image_path' => string|null]
     */
    private function streamOpenAI($model, $systemPrompt, $history, $messageContent, $imageData, ?User $user = null): array
    {
        $currentMessage = $this->buildOpenAIMessage($messageContent, $imageData);
        $fullContent = '';
        $imagePath = null;

        // Injecter la date actuelle et le statut Google Calendar dans le prompt
        $dateNow = now('Europe/Paris')->format('d/m/Y H:i');
        $calendarConnected = $user && !empty($user->google_access_token);
        $calendarNote = $calendarConnected
            ? "\nTu as accès à Google Calendar de l'utilisateur via des outils dédiés. Tu peux :\n- Lister les événements (list_calendar_events)\n- Créer, modifier, supprimer des événements\n- Envoyer par email un résumé des RDV (send_calendar_summary) : utilise TOUJOURS cet outil quand l'utilisateur demande d'envoyer ses RDV par email, ne jamais refuser ou simuler l'envoi.\nFuseau horaire : Europe/Paris. Format des dates : ISO 8601 (ex: 2025-01-20T14:00:00+01:00)."
            : '';
        $searchNote = "\nTu as accès à un outil de recherche web (search_web). RÈGLE ABSOLUE : appelle cet outil IMMÉDIATEMENT et SANS générer aucun texte préalable dès que la question porte sur l'actualité, des prix, des événements récents, la météo ou toute information susceptible d'avoir changé. N'écris jamais de phrases du type \"Je vais vérifier\", \"Je recherche\", \"J'explore\" — appelle l'outil directement.";

        // Mémoires utilisateur persistantes
        $memoryNote = '';
        if ($user) {
            $memories = UserMemory::where('user_id', $user->id)->get();
            if ($memories->isNotEmpty()) {
                $memoryNote = "\nInformations mémorisées sur l'utilisateur (persistantes entre les conversations) :\n";
                foreach ($memories as $mem) {
                    $memoryNote .= "- {$mem->key} : {$mem->value}\n";
                }
            }
        }
        $memoryInstruction = "\nTu peux mémoriser des informations importantes sur l'utilisateur (prénom, préférences, contexte professionnel, etc.) en appelant save_memory, et supprimer une info obsolète avec delete_memory. Fais-le proactivement dès qu'une information utile est mentionnée.";

        $enrichedSystemPrompt = "Date et heure actuelles : {$dateNow}.{$searchNote}{$memoryInstruction}{$memoryNote}{$calendarNote}\n\n{$systemPrompt}";

        // Définition des tools disponibles
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_web',
                    'description' => 'Recherche des informations récentes et actuelles sur le web. À utiliser pour l\'actualité, les prix, la météo, les événements récents, ou toute information pouvant avoir évolué. IMPORTANT : formule la requête dans la même langue que l\'utilisateur (si l\'utilisateur parle français, cherche en français).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'La requête de recherche optimisée pour obtenir les meilleurs résultats. Doit être dans la même langue que la question de l\'utilisateur.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_image',
                    'description' => 'Génère une image avec DALL-E 3. À utiliser quand l\'utilisateur demande de créer, dessiner, illustrer ou modifier une image.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'prompt' => [
                                'type' => 'string',
                                'description' => 'Description précise et détaillée de l\'image à générer, en anglais.',
                            ],
                        ],
                        'required' => ['prompt'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'save_memory',
                    'description' => 'Mémorise une information importante sur l\'utilisateur pour s\'en souvenir lors des futures conversations. À utiliser proactivement dès qu\'un prénom, une profession, une préférence ou tout contexte utile est mentionné.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => [
                                'type' => 'string',
                                'description' => 'Clé courte et descriptive (ex: prénom, profession, langage_préféré, ville, préférence_longueur_réponse, secteur_activité)',
                            ],
                            'value' => [
                                'type' => 'string',
                                'description' => 'Valeur à mémoriser',
                            ],
                        ],
                        'required' => ['key', 'value'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_memory',
                    'description' => 'Supprime une information mémorisée sur l\'utilisateur qui est devenue obsolète ou incorrecte.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => [
                                'type' => 'string',
                                'description' => 'La clé de la mémoire à supprimer',
                            ],
                        ],
                        'required' => ['key'],
                    ],
                ],
            ],
        ];

        // Ajouter les tools Google Calendar si connecté
        if ($calendarConnected) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'list_calendar_events',
                    'description' => 'Lister les événements Google Calendar de l\'utilisateur pour une période donnée.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'time_min'    => ['type' => 'string', 'description' => 'Date/heure début ISO 8601 (ex: 2025-01-20T00:00:00+01:00)'],
                            'time_max'    => ['type' => 'string', 'description' => 'Date/heure fin ISO 8601'],
                            'max_results' => ['type' => 'integer', 'description' => 'Nombre max d\'événements (défaut: 15)'],
                            'period_label'=> ['type' => 'string', 'description' => 'Label lisible de la période, ex: "cette semaine", "aujourd\'hui"'],
                        ],
                        'required' => ['time_min', 'time_max'],
                    ],
                ],
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'create_calendar_event',
                    'description' => 'Créer un nouvel événement dans Google Calendar.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title'       => ['type' => 'string', 'description' => 'Titre de l\'événement'],
                            'start'       => ['type' => 'string', 'description' => 'Date/heure début ISO 8601'],
                            'end'         => ['type' => 'string', 'description' => 'Date/heure fin ISO 8601'],
                            'description' => ['type' => 'string', 'description' => 'Description optionnelle'],
                            'location'    => ['type' => 'string', 'description' => 'Lieu optionnel'],
                            'color'            => [
                                'type' => 'string',
                                'description' => 'Couleur de l\'événement (optionnel)',
                                'enum' => ['lavande', 'sauge', 'raisin', 'flamant', 'banane', 'mandarine', 'paon', 'graphite', 'myrtille', 'basilic', 'tomate'],
                            ],
                            'reminder_minutes' => [
                                'type' => 'integer',
                                'description' => 'Envoyer un rappel N minutes avant l\'événement (ex: 30, 60, 1440 pour 24h). S\'applique à Google Calendar et par email.',
                            ],
                        ],
                        'required' => ['title', 'start', 'end'],
                    ],
                ],
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'delete_calendar_event',
                    'description' => 'Supprimer un événement Google Calendar à partir de son ID.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'event_id'    => ['type' => 'string', 'description' => 'ID de l\'événement (visible dans la liste)'],
                            'event_title' => ['type' => 'string', 'description' => 'Titre de l\'événement pour confirmation'],
                        ],
                        'required' => ['event_id'],
                    ],
                ],
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'update_calendar_event',
                    'description' => 'Modifier un événement Google Calendar existant.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'event_id'    => ['type' => 'string', 'description' => 'ID de l\'événement'],
                            'title'       => ['type' => 'string', 'description' => 'Nouveau titre (optionnel)'],
                            'start'       => ['type' => 'string', 'description' => 'Nouvelle date/heure début ISO 8601 (optionnel)'],
                            'end'         => ['type' => 'string', 'description' => 'Nouvelle date/heure fin ISO 8601 (optionnel)'],
                            'description' => ['type' => 'string', 'description' => 'Nouvelle description (optionnel)'],
                            'location'    => ['type' => 'string', 'description' => 'Nouveau lieu (optionnel)'],
                        ],
                        'required' => ['event_id'],
                    ],
                ],
            ];
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'send_calendar_summary',
                    'description' => 'Envoyer par email un résumé des événements Google Calendar de l\'utilisateur pour une période donnée.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'time_min'     => ['type' => 'string', 'description' => 'Date/heure début ISO 8601'],
                            'time_max'     => ['type' => 'string', 'description' => 'Date/heure fin ISO 8601'],
                            'period_label' => ['type' => 'string', 'description' => 'Label lisible de la période, ex: "cette semaine", "aujourd\'hui"'],
                        ],
                        'required' => ['time_min', 'time_max', 'period_label'],
                    ],
                ],
            ];
        }

        // GPT-5+ n'accepte pas max_tokens, il faut max_completion_tokens
        $isGpt5 = str_starts_with($model, 'gpt-5');
        $tokenParam = $isGpt5 ? 'max_completion_tokens' : 'max_tokens';

        $streamParams = [
            'model'        => $model,
            'messages'     => array_merge(
                [['role' => 'system', 'content' => $enrichedSystemPrompt]],
                $history,
                [$currentMessage]
            ),
            $tokenParam    => 2000,
            'tools'        => $tools,
            'tool_choice'  => 'auto',
        ];

        // GPT-5+ peut rejeter temperature (comme les modèles o-series)
        if (!$isGpt5) {
            $streamParams['temperature'] = 0.7;
        }

        try {
            $stream = OpenAI::chat()->createStreamed($streamParams);
        } catch (\Exception $e) {
            echo "data: " . json_encode(['chunk' => '❌ Erreur API OpenAI : ' . $e->getMessage()]) . "\n\n";
            ob_flush(); flush();
            return ['text' => $e->getMessage(), 'image_path' => null];
        }

        $toolCalls = [];

        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta;
            $finishReason = $response->choices[0]->finishReason;

            // Texte normal
            $chunk = $delta->content ?? '';
            if ($chunk !== '') {
                $fullContent .= $chunk;
                echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                ob_flush();
                flush();
            }

            // Accumuler les tool calls streamés
            if (!empty($delta->toolCalls)) {
                // Effacer le texte pré-tool éventuellement généré
                if (!empty($fullContent)) {
                    $fullContent = '';
                    echo "data: " . json_encode(['clear_chunks' => true]) . "\n\n";
                    ob_flush(); flush();
                }
                foreach ($delta->toolCalls as $tc) {
                    $idx = $tc->index ?? 0;
                    if (!isset($toolCalls[$idx])) {
                        $toolCalls[$idx] = ['id' => '', 'name' => '', 'arguments' => ''];
                    }
                    if (!empty($tc->id)) {
                        $toolCalls[$idx]['id'] = $tc->id;
                    }
                    if (!empty($tc->function->name)) {
                        $toolCalls[$idx]['name'] .= $tc->function->name;
                    }
                    if (!empty($tc->function->arguments)) {
                        $toolCalls[$idx]['arguments'] .= $tc->function->arguments;
                    }
                }
            }

            // Exécuter les tool calls une fois le stream terminé
            if ($finishReason === 'tool_calls' && !empty($toolCalls)) {
                $silentToolResults = []; // tool calls silencieux → follow-up IA

                foreach ($toolCalls as $toolCall) {
                    $args = json_decode($toolCall['arguments'], true) ?? [];

                    // ── RECHERCHE WEB ───────────────────────────────────────────────
                    if ($toolCall['name'] === 'search_web') {
                        $query = $args['query'] ?? $messageContent;

                        echo "data: " . json_encode(['searching_web' => true, 'query' => $query]) . "\n\n";
                        ob_flush(); flush();

                        try {
                            $results = $this->searchWeb($query);
                            if ($results) {
                                // Envoyer les cartes sources au frontend
                                echo "data: " . json_encode(['search_results' => $results['data']]) . "\n\n";
                                ob_flush(); flush();
                                // Passer les résultats bruts à l'IA pour synthèse conversationnelle
                                $silentToolResults[$toolCall['id']] = $results['text'];
                            } else {
                                $err = "❌ Aucun résultat trouvé pour : *{$query}*";
                                $fullContent = $err;
                                echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                                ob_flush(); flush();
                            }
                        } catch (\Exception $e) {
                            $err = "❌ Erreur de recherche : " . $e->getMessage();
                            $fullContent = $err;
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                        }

                    // ── GÉNÉRATION D'IMAGE ──────────────────────────────────────────
                    } elseif ($toolCall['name'] === 'generate_image' && $user) {
                        $prompt = $args['prompt'] ?? $messageContent;

                        echo "data: " . json_encode(['generating_image' => true]) . "\n\n";
                        ob_flush(); flush();

                        try {
                            $imagePath   = $this->generateImageWithDalle($prompt, $user->id);
                            $imageUrl    = Storage::url($imagePath);
                            $fullContent = $prompt;

                            echo "data: " . json_encode([
                                'image_url'    => $imageUrl,
                                'image_prompt' => $prompt,
                            ]) . "\n\n";
                            ob_flush(); flush();
                        } catch (\Exception $e) {
                            $err = "\n\n❌ Erreur lors de la génération de l'image : " . $e->getMessage();
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                            $fullContent .= $err;
                        }

                    // ── LISTE DES ÉVÉNEMENTS ────────────────────────────────────────
                    } elseif ($toolCall['name'] === 'list_calendar_events') {
                        try {
                            $calendar   = new GoogleCalendarService($user);
                            $events     = $calendar->getEvents(
                                $args['time_min'],
                                $args['time_max'],
                                $args['max_results'] ?? 15
                            );
                            $period      = $args['period_label'] ?? '';
                            $fullContent = GoogleCalendarService::formatEventsList($events, $period, true);  // avec IDs pour l'IA
                            $forUser     = GoogleCalendarService::formatEventsList($events, $period, false); // sans IDs pour l'affichage

                            echo "data: " . json_encode(['chunk' => $forUser]) . "\n\n";
                            ob_flush(); flush();
                        } catch (\Exception $e) {
                            $err = "❌ " . $e->getMessage();
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                            $fullContent = $err;
                        }

                    // ── CRÉER UN ÉVÉNEMENT ──────────────────────────────────────────
                    } elseif ($toolCall['name'] === 'create_calendar_event') {
                        try {
                            $reminderMinutes = isset($args['reminder_minutes']) ? (int) $args['reminder_minutes'] : null;
                            $calendar = new GoogleCalendarService($user);
                            $event    = $calendar->createEvent(
                                $args['title'],
                                $args['start'],
                                $args['end'],
                                $args['description']  ?? null,
                                $args['location']     ?? null,
                                $args['color']        ?? null,
                                $reminderMinutes
                            );

                            // Stocker le rappel email si demandé
                            if ($reminderMinutes && !empty($event['id'])) {
                                $eventStart = new \DateTime($args['start']);
                                EmailReminder::create([
                                    'user_id'         => $user->id,
                                    'google_event_id' => $event['id'],
                                    'event_title'     => $args['title'],
                                    'event_start'     => $eventStart,
                                    'event_location'  => $args['location'] ?? null,
                                    'remind_at'       => (clone $eventStart)->modify("-{$reminderMinutes} minutes"),
                                ]);
                            }

                            $formatted   = GoogleCalendarService::formatCreatedEvent($event, $reminderMinutes);
                            $fullContent = $formatted;

                            echo "data: " . json_encode(['chunk' => $formatted]) . "\n\n";
                            ob_flush(); flush();
                        } catch (\Exception $e) {
                            $err = "❌ " . $e->getMessage();
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                            $fullContent = $err;
                        }

                    // ── MODIFIER UN ÉVÉNEMENT ───────────────────────────────────────
                    } elseif ($toolCall['name'] === 'update_calendar_event') {
                        try {
                            $calendar = new GoogleCalendarService($user);
                            $updates  = array_filter([
                                'summary'     => $args['title']       ?? null,
                                'start'       => $args['start']       ?? null,
                                'end'         => $args['end']         ?? null,
                                'description' => $args['description'] ?? null,
                                'location'    => $args['location']    ?? null,
                            ]);
                            $event    = $calendar->updateEvent($args['event_id'], $updates);
                            $title    = $event['summary'] ?? $args['event_id'];
                            $msg      = "✅ Événement **{$title}** modifié avec succès.";
                            $fullContent = $msg;

                            echo "data: " . json_encode(['chunk' => $msg]) . "\n\n";
                            ob_flush(); flush();
                        } catch (\Exception $e) {
                            $err = "❌ " . $e->getMessage();
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                            $fullContent = $err;
                        }

                    // ── SUPPRIMER UN ÉVÉNEMENT ──────────────────────────────────────
                    } elseif ($toolCall['name'] === 'delete_calendar_event') {
                        try {
                            $calendar = new GoogleCalendarService($user);
                            $calendar->deleteEvent($args['event_id']);
                            $title    = $args['event_title'] ?? $args['event_id'];
                            $msg      = "🗑️ Événement **{$title}** supprimé.";
                            $fullContent = $msg;

                            echo "data: " . json_encode(['chunk' => $msg]) . "\n\n";
                            ob_flush(); flush();
                        } catch (\Exception $e) {
                            $err = "❌ " . $e->getMessage();
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                            $fullContent = $err;
                        }

                    // ── ENVOYER RÉSUMÉ PAR EMAIL ────────────────────────────────────
                    } elseif ($toolCall['name'] === 'send_calendar_summary') {
                        try {
                            $calendar    = new GoogleCalendarService($user);
                            $events      = $calendar->getEvents($args['time_min'], $args['time_max'], 50);
                            $periodLabel = $args['period_label'];
                            $recipient   = $user->email ?: env('CONTACT_EMAIL');

                            \Mail::to($recipient)->send(new \App\Mail\CalendarSummaryMail(
                                $user,
                                $events,
                                $periodLabel,
                                $args['time_min'],
                                $args['time_max'],
                            ));

                            $count   = count($events);
                            $msg     = "📧 Résumé envoyé à **{$recipient}** — **{$count} événement" . ($count > 1 ? 's' : '') . "** pour {$periodLabel}.";
                            $fullContent = $msg;

                            echo "data: " . json_encode(['chunk' => $msg]) . "\n\n";
                            ob_flush(); flush();
                        } catch (\Exception $e) {
                            $err = "❌ " . $e->getMessage();
                            echo "data: " . json_encode(['chunk' => $err]) . "\n\n";
                            ob_flush(); flush();
                            $fullContent = $err;
                        }

                    // ── MÉMORISER (silencieux → follow-up) ────────────────────────
                    } elseif ($toolCall['name'] === 'save_memory') {
                        $key   = trim($args['key'] ?? '');
                        $value = trim($args['value'] ?? '');

                        if ($key && $value && $user) {
                            UserMemory::updateOrCreate(
                                ['user_id' => $user->id, 'key' => $key],
                                ['value' => $value]
                            );
                            $silentToolResults[$toolCall['id']] = "Mémoire sauvegardée : {$key} = {$value}";
                        } else {
                            $silentToolResults[$toolCall['id']] = "Erreur : paramètres manquants.";
                        }

                    // ── SUPPRIMER MÉMOIRE (silencieux → follow-up) ────────────────
                    } elseif ($toolCall['name'] === 'delete_memory') {
                        $key = trim($args['key'] ?? '');

                        if ($key && $user) {
                            $deleted = UserMemory::where('user_id', $user->id)
                                ->where('key', $key)
                                ->delete();
                            $silentToolResults[$toolCall['id']] = $deleted
                                ? "Mémoire supprimée : {$key}"
                                : "Aucune mémoire trouvée pour la clé : {$key}";
                        } else {
                            $silentToolResults[$toolCall['id']] = "Erreur : clé manquante.";
                        }
                    }
                }

                // Follow-up : si des tools silencieux ont été exécutés, relancer l'IA
                // pour qu'elle génère une réponse naturelle à l'utilisateur
                if (!empty($silentToolResults)) {
                    $assistantToolCallsMsg = [
                        'role'       => 'assistant',
                        'content'    => null,
                        'tool_calls' => array_values(array_map(fn($tc) => [
                            'id'       => $tc['id'] ?: ('call_' . uniqid()),
                            'type'     => 'function',
                            'function' => ['name' => $tc['name'], 'arguments' => $tc['arguments']],
                        ], $toolCalls)),
                    ];

                    $toolResultMessages = [];
                    foreach ($toolCalls as $tc) {
                        if (isset($silentToolResults[$tc['id']])) {
                            $toolResultMessages[] = [
                                'role'         => 'tool',
                                'tool_call_id' => $tc['id'] ?: ('call_' . uniqid()),
                                'content'      => $silentToolResults[$tc['id']],
                            ];
                        }
                    }

                    $followUpMessages = array_merge(
                        [['role' => 'system', 'content' => $enrichedSystemPrompt . "\n\nIMPORTANT : Tu viens d'exécuter un ou plusieurs outils. Réponds DIRECTEMENT avec ta synthèse ou ta réponse. N'écris PAS de phrases introductives comme \"Je vais vérifier\", \"Voici ce que j'ai trouvé\", \"Je recherche\", \"J'explore\", \"Je consulte\", etc. Va droit au but."]],
                        $history,
                        [$currentMessage],
                        [$assistantToolCallsMsg],
                        $toolResultMessages
                    );

                    $followUpIsGpt5      = str_starts_with($model, 'gpt-5');
                    $followUpTokenParam  = $followUpIsGpt5 ? 'max_completion_tokens' : 'max_tokens';
                    $followUpParams      = [
                        'model'             => $model,
                        'messages'          => $followUpMessages,
                        $followUpTokenParam => 2000,
                        'tools'             => $tools,
                        'tool_choice'       => 'none', // interdit tout nouvel appel d'outil dans la synthèse
                    ];
                    if (!$followUpIsGpt5) {
                        $followUpParams['temperature'] = 0.7;
                    }

                    $followUpStream = OpenAI::chat()->createStreamed($followUpParams);

                    $fullContent = '';
                    foreach ($followUpStream as $followUpResponse) {
                        $chunk = $followUpResponse->choices[0]->delta->content ?? '';
                        if ($chunk !== '') {
                            $fullContent .= $chunk;
                            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                            ob_flush(); flush();
                        }
                    }
                }
            }
        }

        return ['text' => $fullContent, 'image_path' => $imagePath];
    }

    /**
     * Générer une image avec DALL-E 3, la télécharger et la sauvegarder
     */
    private function generateImageWithDalle(string $prompt, int $userId): string
    {
        $response = OpenAI::images()->create([
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
        ]);

        $imageUrl = $response->data[0]->url;

        $imageContent = Http::timeout(60)->get($imageUrl)->body();
        $path = 'chat-images/' . $userId . '/dalle-' . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $imageContent);

        return $path;
    }

    /**
     * Appel Anthropic (non-streaming)
     */
    private function callAnthropic($model, $systemPrompt, $history, $messageContent, $imageData)
    {
        $messages = $this->buildAnthropicMessages($history, $messageContent, $imageData);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 2000,
            'system' => $systemPrompt,
            'messages' => $messages,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Erreur Anthropic: ' . $response->body());
        }

        return $response->json()['content'][0]['text'];
    }

    /**
     * Streaming Anthropic
     */
    private function streamAnthropic($model, $systemPrompt, $history, $messageContent, $imageData)
    {
        $messages = $this->buildAnthropicMessages($history, $messageContent, $imageData);
        $fullContent = '';

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->withOptions([
            'stream' => true,
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 2000,
            'system' => $systemPrompt,
            'messages' => $messages,
            'stream' => true,
        ]);

        $body = $response->getBody();

        while (!$body->eof()) {
            $line = $this->readLine($body);

            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);

                if ($data === '[DONE]') {
                    break;
                }

                $json = json_decode($data, true);

                if ($json && isset($json['type'])) {
                    if ($json['type'] === 'content_block_delta' && isset($json['delta']['text'])) {
                        $chunk = $json['delta']['text'];
                        $fullContent .= $chunk;
                        echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
            }
        }

        return $fullContent;
    }

    /**
     * Lire une ligne du stream
     */
    private function readLine($body)
    {
        $line = '';
        while (!$body->eof()) {
            $char = $body->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }

    /**
     * Construire le message pour OpenAI
     */
    private function buildOpenAIMessage($messageContent, $imageData)
    {
        if ($imageData) {
            $contentArray = [];
            if (!empty($messageContent)) {
                $contentArray[] = [
                    'type' => 'text',
                    'text' => $messageContent,
                ];
            }
            $contentArray[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageData,
                ],
            ];
            return [
                'role' => 'user',
                'content' => $contentArray,
            ];
        }

        return [
            'role' => 'user',
            'content' => $messageContent,
        ];
    }

    /**
     * Construire les messages pour Anthropic
     */
    private function buildAnthropicMessages($history, $messageContent, $imageData)
    {
        $messages = [];

        // Ajouter l'historique
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        // Ajouter le message actuel
        if ($imageData) {
            $contentArray = [];

            // Extraire le type et les données de l'image base64
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
                $mediaType = 'image/' . $matches[1];
                $base64Data = $matches[2];

                $contentArray[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mediaType,
                        'data' => $base64Data,
                    ],
                ];
            }

            if (!empty($messageContent)) {
                $contentArray[] = [
                    'type' => 'text',
                    'text' => $messageContent,
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => $contentArray,
            ];
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $messageContent,
            ];
        }

        return $messages;
    }

    /**
     * Générer un titre court avec l'IA pour une conversation
     */
    public function generateTitle(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $firstUserMessage = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at', 'asc')
            ->value('content');

        if (!$firstUserMessage) {
            return response()->json(['title' => $conversation->title]);
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu génères des titres courts pour des conversations. Réponds uniquement avec le titre, sans guillemets ni ponctuation finale, en 3 à 6 mots maximum.'],
                    ['role' => 'user', 'content' => 'Titre pour : ' . mb_substr($firstUserMessage, 0, 300)],
                ],
                'max_tokens' => 20,
                'temperature' => 0.5,
            ]);

            $title = trim($response->choices[0]->message->content, " \"'.");
            $conversation->update(['title' => $title]);

            return response()->json(['title' => $title]);
        } catch (\Exception $e) {
            return response()->json(['title' => $conversation->title]);
        }
    }

    /**
     * Régénérer la dernière réponse IA (streaming)
     */
    public function regenerate(Request $request, Conversation $conversation)
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'model' => 'nullable|string',
            'system_prompt' => 'nullable|string|max:3000',
        ]);

        $model = $validated['model'] ?? 'gpt-4o-mini';
        $provider = self::$models[$model]['provider'] ?? 'openai';
        $systemPrompt = $validated['system_prompt'] ?? 'Tu es un assistant utile et amical. Tu peux générer des images sur demande. Tu réponds en français de manière concise et claire.';
        $user = $request->user();

        // Supprimer le dernier message assistant
        $lastAssistant = $conversation->messages()
            ->where('role', 'assistant')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastAssistant) {
            $lastAssistant->delete();
        }

        // Récupérer le dernier message utilisateur
        $lastUser = $conversation->messages()
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastUser) {
            return response()->json(['message' => 'Aucun message utilisateur trouvé'], 400);
        }

        // Historique sans le dernier message utilisateur (tronqué)
        $history = $this->truncateHistory(
            $conversation->messages()
                ->where('id', '!=', $lastUser->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
                ->toArray()
        );

        return response()->stream(function () use ($conversation, $history, $lastUser, $systemPrompt, $model, $provider, $user) {
            $fullContent = '';
            $generatedImagePath = null;
            try {
                if ($provider === 'anthropic') {
                    $fullContent = $this->streamAnthropic($model, $systemPrompt, $history, $lastUser->content, null);
                } else {
                    $result = $this->streamOpenAI($model, $systemPrompt, $history, $lastUser->content, null, $user);
                    $fullContent = $result['text'];
                    $generatedImagePath = $result['image_path'];
                }

                $messageData = ['role' => 'assistant', 'content' => $fullContent];
                if ($generatedImagePath) {
                    $messageData['has_image'] = true;
                    $messageData['image_path'] = $generatedImagePath;
                }

                $assistantMessage = $conversation->messages()->create($messageData);

                $conversation->touch();

                echo "data: " . json_encode(['done' => true, 'assistant_message' => $assistantMessage]) . "\n\n";
                ob_flush();
                flush();
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Sauvegarder une image base64 sur le disque public
     * Retourne le chemin relatif stocké en BDD
     */
    private function saveImage(string $base64Image, int $userId): string
    {
        $ext = 'jpg';
        $data = $base64Image;

        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64Image, $matches)) {
            $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
            $data = base64_decode($matches[2]);
        } else {
            $data = base64_decode($base64Image);
        }

        $path = 'chat-images/' . $userId . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /**
     * Tronquer l'historique pour rester sous le budget de tokens
     * Conserve les messages les plus récents en priorité
     * Estimation : 1 token ≈ 4 caractères
     */
    private function truncateHistory(array $history, int $maxTokens = 12000): array
    {
        $totalTokens = 0;
        $truncated = [];

        foreach (array_reverse($history) as $message) {
            $tokens = (int) ceil(mb_strlen($message['content']) / 4);
            if ($totalTokens + $tokens > $maxTokens) {
                break;
            }
            $totalTokens += $tokens;
            array_unshift($truncated, $message);
        }

        return $truncated;
    }

    /**
     * Recherche web avec Brave Search
     */
    private function searchWeb($query)
    {
        $apiKey = config('services.brave.api_key');

        if (empty($apiKey)) {
            throw new \Exception('Clé API Brave Search non configurée (BRAVE_SEARCH_API_KEY manquante dans .env)');
        }

        $response = Http::withHeaders([
            'Accept'               => 'application/json',
            'X-Subscription-Token' => $apiKey,
        ])->get('https://api.search.brave.com/res/v1/web/search', [
            'q'           => $query,
            'count'       => 5,
            'country'     => 'fr',
            'search_lang' => 'fr',
            'ui_lang'     => 'fr-FR',
        ]);

        if (!$response->successful()) {
            $body = $response->json();
            $errorMsg = is_string($body['message'] ?? null) ? $body['message']
                      : (is_string($body['error']   ?? null) ? $body['error']
                      : json_encode($body));
            throw new \Exception("Brave Search: {$errorMsg}");
        }

        $data = $response->json();
        $results = $data['web']['results'] ?? [];

        // Normalise un champ qui peut être string ou array imbriqué, et nettoie le HTML
        $clean = function($v) {
            $s = is_array($v) ? ($v['main'] ?? implode(' ', array_filter($v, 'is_string'))) : (string)($v ?? '');
            return html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        // Texte brut pour le stockage en base de données
        $text = "Résultats de recherche web pour: \"{$query}\"\n\nSources:\n";
        foreach ($results as $index => $result) {
            $num   = $index + 1;
            $title = $clean($result['title'] ?? '');
            $url   = $clean($result['url']   ?? '');
            $desc  = $clean($result['description'] ?? '');
            $text .= "{$num}. {$title}\n   URL: {$url}\n   {$desc}\n\n";
        }

        // Données structurées pour l'affichage frontend en cartes
        $sources = [];
        foreach ($results as $result) {
            $sources[] = [
                'title'   => $clean($result['title']       ?? ''),
                'url'     => $clean($result['url']         ?? ''),
                'content' => $clean($result['description'] ?? ''),
            ];
        }

        return [
            'text' => $text,
            'data' => [
                'query'   => $query,
                'answer'  => null,
                'sources' => $sources,
            ],
        ];
    }

    /**
     * Éditer un message utilisateur et supprimer les messages suivants
     */
    public function editMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();

        if ($conversation->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($message->conversation_id !== $conversation->id || $message->role !== 'user') {
            return response()->json(['message' => 'Message invalide'], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:20000',
        ]);

        $message->update(['content' => $validated['content']]);

        // Supprimer tous les messages après ce message
        $conversation->messages()->where('id', '>', $message->id)->delete();

        return response()->json(['message' => 'updated', 'id' => $message->id]);
    }

    /**
     * Activer le partage d'une conversation (génère un token public)
     */
    public function share(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $token = $conversation->share_token ?? Str::random(48);
        $conversation->update(['share_token' => $token]);

        return response()->json([
            'url' => route('share.conversation', $token),
            'token' => $token,
        ]);
    }

    /**
     * Désactiver le partage d'une conversation
     */
    public function unshare(Request $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $conversation->update(['share_token' => null]);

        return response()->json(['message' => 'unshared']);
    }
}
