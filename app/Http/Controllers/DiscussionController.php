<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyNewDiscussionMessage;
use App\Models\Discussion;
use App\Models\User;
use App\Services\Unread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    /**
     * Nombre de messages chargés d'emblée dans un fil.
     */
    private const HISTORY_LIMIT = 100;

    /**
     * Liste des discussions, éventuellement avec un fil ouvert.
     */
    public function index(Request $request, ?Discussion $discussion = null)
    {
        $user = $request->user();

        if ($discussion?->exists) {
            $discussion->load('participants');
            $this->authorize('view', $discussion);
        }

        $messages = collect();

        if ($discussion?->exists) {
            $messages = $discussion->messages()
                ->with('author:id,name,avatar_path')
                ->latest('id')
                ->limit(self::HISTORY_LIMIT)
                ->get()
                ->reverse()
                ->values();

            $this->markAsRead($discussion, $user->id);
        }

        return view('messages.index', [
            'discussions'   => $this->rosterFor($user->id),
            'unread'        => Unread::perDiscussionFor($user->id),
            'discussion'    => $discussion?->exists ? $discussion : null,
            'messages'      => $messages,
            'contacts'      => $this->contactsFor($user->id),
        ]);
    }

    /**
     * Ouvre (ou crée) la conversation à deux avec quelqu'un.
     */
    public function openDirect(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Impossible de démarrer une conversation avec soi-même.']);
        }

        if (!$user->is_active) {
            return back()->withErrors(['user' => 'Ce compte est désactivé.']);
        }

        $discussion = Discussion::findOrCreateDirect($request->user()->id, $user->id);

        return redirect()->route('messages.show', $discussion);
    }

    /**
     * Crée un groupe et y place son auteur.
     */
    public function storeGroup(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:80'],
            'members'      => ['required', 'array', 'min:1'],
            'members.*'    => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ], [
            'name.required'    => 'Donnez un nom au groupe.',
            'members.required' => 'Choisissez au moins une personne.',
        ]);

        $members = collect($validated['members'])
            ->push($request->user()->id)
            ->unique()
            ->values();

        $discussion = DB::transaction(function () use ($validated, $members, $request) {
            $discussion = Discussion::create([
                'name'            => $validated['name'],
                'is_group'        => true,
                'created_by'      => $request->user()->id,
                'last_message_at' => now(),
            ]);

            $discussion->participants()->attach($members->all());

            return $discussion;
        });

        return redirect()->route('messages.show', $discussion);
    }

    /**
     * Poste un message.
     */
    public function send(Request $request, Discussion $discussion): JsonResponse
    {
        $discussion->load('participants');
        $this->authorize('send', $discussion);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'Le message est vide.',
            'body.max'      => 'Le message ne doit pas dépasser 5000 caractères.',
        ]);

        $message = $discussion->messages()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        $discussion->update(['last_message_at' => $message->created_at]);
        $this->markAsRead($discussion, $request->user()->id);

        // afterResponse() plutôt qu'une vraie mise en file : les notifications
        // partent une fois la réponse envoyée, sans imposer de « queue:work »
        // en production. Le jour où un worker tournera, retirer l'appel suffit
        // à basculer sur la file.
        NotifyNewDiscussionMessage::dispatch($message->id)->afterResponse();

        $message->setRelation('author', $request->user());

        return response()->json(['message' => $message->toPayload($request->user()->id)], 201);
    }

    /**
     * Messages postérieurs à un identifiant donné — appelé par le sondage.
     */
    public function poll(Request $request, Discussion $discussion): JsonResponse
    {
        $discussion->load('participants');
        $this->authorize('view', $discussion);

        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $messages = $discussion->messages()
            ->with('author:id,name,avatar_path')
            ->where('id', '>', $validated['after'] ?? 0)
            ->orderBy('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();

        if ($messages->isNotEmpty()) {
            $this->markAsRead($discussion, $request->user()->id);
        }

        return response()
            ->json([
                'messages' => $messages->map(fn ($m) => $m->toPayload($request->user()->id))->all(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Compteurs de non-lus, pour la pastille de navigation.
     */
    public function unread(Request $request): JsonResponse
    {
        return response()
            ->json([
                'total'          => Unread::totalFor($request->user()->id),
                'per_discussion' => Unread::perDiscussionFor($request->user()->id),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Quitte un groupe.
     */
    public function leave(Request $request, Discussion $discussion)
    {
        $discussion->load('participants');
        $this->authorize('leave', $discussion);

        $discussion->participants()->detach($request->user()->id);

        return redirect()->route('messages.index')
            ->with('success', 'Vous avez quitté « ' . $discussion->titleFor($request->user()->id) . ' ».');
    }

    /**
     * Ajoute des personnes à un groupe (réservé à son créateur).
     */
    public function addParticipants(Request $request, Discussion $discussion)
    {
        $discussion->load('participants');
        $this->authorize('manage', $discussion);

        $validated = $request->validate([
            'members'   => ['required', 'array', 'min:1'],
            'members.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);

        // syncWithoutDetaching : réinviter un membre déjà présent ne doit pas
        // réinitialiser sa frontière de lecture.
        $discussion->participants()->syncWithoutDetaching($validated['members']);

        return redirect()->route('messages.show', $discussion);
    }

    // ---- Helpers ----

    /**
     * Fils de l'utilisateur, le plus récemment actif en tête.
     */
    private function rosterFor(int $userId)
    {
        return Discussion::query()
            ->whereHas('participants', fn ($q) => $q->whereKey($userId))
            ->with([
                'participants:id,name,avatar_path,last_seen_at',
                // Pas de liste de colonnes ici : latestOfMany() ajoute une
                // sous-requête jointe, où des noms non qualifiés deviennent
                // ambigus (discussion_id existe des deux côtés).
                'lastMessage',
            ])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Personnes joignables : tous les comptes actifs sauf soi.
     */
    private function contactsFor(int $userId)
    {
        return User::query()
            ->where('is_active', true)
            ->whereKeyNot($userId)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path', 'last_seen_at']);
    }

    private function markAsRead(Discussion $discussion, int $userId): void
    {
        $discussion->participants()->updateExistingPivot($userId, ['last_read_at' => now()]);
    }
}
