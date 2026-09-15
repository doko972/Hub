<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyNewDiscussionMessage;
use App\Models\Discussion;
use App\Models\DiscussionAttachment;
use App\Models\DiscussionMessage;
use App\Models\User;
use App\Services\GifSearch;
use App\Services\Typing;
use App\Services\Unread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    /**
     * Nombre de messages chargés d'emblée dans un fil.
     */
    private const HISTORY_LIMIT = 100;

    /**
     * Nombre de messages ramenés à chaque remontée dans l'historique.
     */
    private const PAGE_SIZE = 50;

    /**
     * Nombre maximum de résultats de recherche renvoyés.
     */
    private const SEARCH_LIMIT = 40;

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

        $messages   = collect();
        $hasOlder   = false;
        $focusedId  = null;

        if ($discussion?->exists) {
            // Un résultat de recherche peut viser un message ancien : on ouvre
            // alors le fil autour de lui plutôt qu'à la fin.
            $focusedId = (int) $request->query('at') ?: null;

            if ($focusedId && !$discussion->visibleMessagesFor($user->id)->whereKey($focusedId)->exists()) {
                $focusedId = null;
            }

            $messages = $this->windowFor($discussion, $user->id, $focusedId);
            $hasOlder = $messages->isNotEmpty()
                && $discussion->visibleMessagesFor($user->id)->where('id', '<', $messages->first()->id)->exists();

            $this->markAsRead($discussion, $user->id);
        }

        return view('messages.index', [
            'discussions'   => $this->rosterFor($user->id),
            'unread'        => Unread::perDiscussionFor($user->id),
            'discussion'    => $discussion?->exists ? $discussion : null,
            'messages'      => $messages,
            'hasOlder'      => $hasOlder,
            'focusedId'     => $focusedId,
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

        $config     = config('messaging.attachments');
        $extensions = implode(',', $config['allowed_extensions']);

        $validated = $request->validate([
            // Un message peut n'être qu'une pièce jointe, mais pas être vide
            // des deux côtés.
            'body'          => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'attachments'   => ['nullable', 'array', 'max:' . $config['max_files']],
            // mimes vérifie le contenu réel, extensions le nom annoncé : les
            // deux ensemble arrêtent un script renommé en .jpg comme une image
            // renommée en .php.
            'attachments.*' => [
                'file',
                'max:' . $config['max_size_kb'],
                'mimes:' . $extensions,
                'extensions:' . $extensions,
            ],
        ], [
            'body.required_without' => 'Écrivez un message ou joignez un fichier.',
            'body.max'              => 'Le message ne doit pas dépasser 5000 caractères.',
            'attachments.max'       => 'Pas plus de ' . $config['max_files'] . ' fichiers par message.',
            'attachments.*.max'     => 'Chaque fichier doit rester sous ' . round($config['max_size_kb'] / 1024) . ' Mo.',
            'attachments.*.mimes'   => 'Ce type de fichier n\'est pas autorisé.',
            'attachments.*.extensions' => 'Ce type de fichier n\'est pas autorisé.',
        ]);

        $message = $discussion->messages()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'] ?? '',
        ]);

        $this->storeAttachments($request, $message, $discussion->id);

        $discussion->update(['last_message_at' => $message->created_at]);
        $this->markAsRead($discussion, $request->user()->id);
        Typing::clear($discussion->id, $request->user()->id);

        // afterResponse() plutôt qu'une vraie mise en file : les notifications
        // partent une fois la réponse envoyée, sans imposer de « queue:work »
        // en production. Le jour où un worker tournera, retirer l'appel suffit
        // à basculer sur la file.
        NotifyNewDiscussionMessage::dispatch($message->id)->afterResponse();

        $message->setRelation('author', $request->user());
        $message->load('attachments');

        return response()->json(['message' => $message->toPayload($request->user()->id)], 201);
    }

    /**
     * Modifie le texte d'un de ses messages.
     */
    public function updateMessage(Request $request, Discussion $discussion, DiscussionMessage $message): JsonResponse
    {
        $this->ensureBelongsTo($message, $discussion);
        $this->authorize('update', $message);

        // Un message réduit à une pièce jointe n'a pas de texte à modifier ;
        // en revanche on ne doit pas pouvoir vider un message qui n'a que ça.
        $validated = $request->validate([
            'body' => [
                $message->attachments()->exists() ? 'nullable' : 'required',
                'string',
                'max:5000',
            ],
        ], [
            'body.required' => 'Le message ne peut pas être vide.',
        ]);

        $message->update([
            'body'      => $validated['body'] ?? '',
            'edited_at' => now(),
        ]);

        $message->load(['author', 'attachments', 'reactions']);

        return response()->json(['message' => $message->toPayload($request->user()->id)]);
    }

    /**
     * Supprime un de ses messages.
     *
     * Suppression douce : le sondage doit pouvoir annoncer la disparition aux
     * autres participants, ce qu'une ligne effacée ne permettrait plus. Les
     * pièces jointes, elles, sont réellement retirées du disque.
     */
    public function destroyMessage(Request $request, Discussion $discussion, DiscussionMessage $message): JsonResponse
    {
        $this->ensureBelongsTo($message, $discussion);
        $this->authorize('delete', $message);

        $message->delete();

        return response()->json(['deleted' => $message->id]);
    }

    /**
     * Recherche de GIF, relayée par le serveur pour garder la clé côté serveur.
     */
    public function searchGifs(Request $request, GifSearch $gifs): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:80'],
        ]);

        return response()
            ->json(['gifs' => $gifs->search($validated['q'])])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Rapatrie le GIF choisi et le publie comme pièce jointe.
     *
     * Le fichier est copié chez nous plutôt que référencé : le message reste
     * lisible si Giphy change d'URL, et l'affichage n'expose aucun participant
     * à un tiers.
     */
    public function sendGif(Request $request, Discussion $discussion): JsonResponse
    {
        $discussion->load('participants');
        $this->authorize('send', $discussion);

        $validated = $request->validate([
            'url'         => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:120'],
        ]);

        // Liste blanche d'hôtes : sans elle, cette route ferait télécharger au
        // serveur n'importe quelle URL fournie par le client (SSRF).
        if (!GifSearch::isAllowedUrl($validated['url'])) {
            return response()->json(['message' => 'Source de GIF non autorisée.'], 422);
        }

        try {
            $response = Http::timeout(15)->get($validated['url']);

            if (!$response->successful()) {
                throw new \RuntimeException('téléchargement impossible');
            }
        } catch (\Throwable $e) {
            Log::warning('Rapatriement de GIF en échec', ['exception' => $e]);

            return response()->json(['message' => 'Impossible de récupérer ce GIF.'], 502);
        }

        $contenu = $response->body();

        if (strlen($contenu) > GifSearch::MAX_BYTES) {
            return response()->json(['message' => 'Ce GIF est trop volumineux.'], 422);
        }

        // On ne fait pas confiance au Content-Type annoncé : le contenu doit
        // réellement être une image, sans quoi on stocke n'importe quoi.
        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenu);

        if ($type !== 'image/gif') {
            return response()->json(['message' => 'Le fichier récupéré n\'est pas un GIF.'], 422);
        }

        $message = $discussion->messages()->create([
            'user_id' => $request->user()->id,
            'body'    => '',
        ]);

        $chemin = "discussions/{$discussion->id}/" . Str::uuid() . '.gif';
        Storage::disk(DiscussionAttachment::DISK)->put($chemin, $contenu);

        $message->attachments()->create([
            'path'          => $chemin,
            'original_name' => Str::slug($validated['description'] ?: 'gif') . '.gif',
            'mime_type'     => 'image/gif',
            'size'          => strlen($contenu),
        ]);

        $discussion->update(['last_message_at' => $message->created_at]);
        $this->markAsRead($discussion, $request->user()->id);

        NotifyNewDiscussionMessage::dispatch($message->id)->afterResponse();

        $message->setRelation('author', $request->user());
        $message->load(['attachments', 'reactions']);

        return response()->json(['message' => $message->toPayload($request->user()->id)], 201);
    }

    /**
     * Signale que l'on est en train d'écrire, ou que l'on a cessé.
     */
    public function typing(Request $request, Discussion $discussion): JsonResponse
    {
        $discussion->load('participants');
        $this->authorize('send', $discussion);

        $validated = $request->validate([
            'typing' => ['required', 'boolean'],
        ]);

        $validated['typing']
            ? Typing::mark($discussion->id, $request->user()->id, $request->user()->name)
            : Typing::clear($discussion->id, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Pose ou retire une réaction sur un message.
     *
     * Bascule plutôt que deux routes : cliquer une réaction déjà posée la
     * retire, ce qui correspond à ce que fait l'interface.
     */
    public function toggleReaction(Request $request, Discussion $discussion, DiscussionMessage $message): JsonResponse
    {
        $discussion->load('participants');
        $this->authorize('send', $discussion);

        if ($message->discussion_id !== $discussion->id) {
            abort(404);
        }

        $validated = $request->validate([
            // On valide la forme plutôt qu'une liste figée : seuls des
            // caractères pictographiques (avec modificateurs et jointures)
            // passent, ce qui interdit d'enregistrer du texte arbitraire tout
            // en laissant le champ libre au sélecteur.
            'emoji' => [
                'required',
                'string',
                'regex:/^[\p{Extended_Pictographic}\x{FE0F}\x{200D}\x{1F3FB}-\x{1F3FF}]{1,8}$/u',
            ],
        ], [
            'emoji.regex' => 'Réaction invalide.',
        ]);

        $existante = $message->reactions()
            ->where('user_id', $request->user()->id)
            ->where('emoji', $validated['emoji'])
            ->first();

        if ($existante) {
            $existante->delete();
        } else {
            $message->reactions()->create([
                'user_id' => $request->user()->id,
                'emoji'   => $validated['emoji'],
            ]);
        }

        $message->load('reactions');

        return response()->json([
            'message_id' => $message->id,
            'reactions'  => $message->reactionSummary($request->user()->id),
        ]);
    }

    /**
     * Sert une pièce jointe, après vérification de l'appartenance au fil.
     *
     * C'est le seul chemin d'accès : les fichiers vivent sur le disque privé,
     * hors de public/, donc rien ne peut être atteint par URL devinée.
     */
    public function attachment(Request $request, DiscussionAttachment $attachment)
    {
        $discussion = $attachment->message->discussion;
        $discussion->load('participants');

        $this->authorize('view', $discussion);

        // Même participant, la pièce jointe d'une conversation qu'il a supprimée
        // ne lui est plus servie : 404 plutôt que 403, rien n'est à révéler.
        abort_if($attachment->discussion_message_id <= $discussion->clearedThroughFor($request->user()->id), 404);

        if (!Storage::disk(DiscussionAttachment::DISK)->exists($attachment->path)) {
            abort(404);
        }

        // Les images s'affichent dans le fil ; tout le reste est téléchargé,
        // jamais rendu par le navigateur.
        $disposition = $attachment->isInlineImage() ? 'inline' : 'attachment';

        return Storage::disk(DiscussionAttachment::DISK)->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type'        => $attachment->mime_type,
                'Content-Disposition' => $disposition . '; filename="' . addslashes($attachment->original_name) . '"',
            ]
        );
    }

    /**
     * Enregistre les fichiers joints sur le disque privé.
     */
    private function storeAttachments(Request $request, $message, int $discussionId): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            // Nom aléatoire : le nom d'origine, choisi par l'expéditeur, ne
            // touche jamais le système de fichiers.
            $path = $file->store("discussions/{$discussionId}", DiscussionAttachment::DISK);

            $message->attachments()->create([
                'path'          => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type'     => $file->getMimeType() ?: 'application/octet-stream',
                'size'          => $file->getSize(),
            ]);
        }
    }

    /**
     * Recherche dans les messages de l'utilisateur.
     *
     * Le filtrage par participation est la première clause, pas un ajout : une
     * recherche qui fouillerait toutes les discussions serait une fuite bien
     * plus grave qu'un simple défaut d'affichage.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'q.min' => 'Saisissez au moins deux caractères.',
        ]);

        $userId = $request->user()->id;
        $terme  = trim($validated['q']);

        // Les caractères jokers de LIKE sont neutralisés : sans cela, un « % »
        // saisi par mégarde retournerait toute la conversation.
        // « ! » comme caractère d échappement plutôt que l antislash : SQLite
        // n en retient aucun par défaut (il faut le déclarer), et « ! » évite
        // les empilements d antislashs entre PHP et SQL.
        $motif = "%" . str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $terme) . "%";

        $resultats = DiscussionMessage::query()
            // La frontière de suppression se lit sur la ligne de participation :
            // une conversation supprimée ne doit pas ressortir par la recherche.
            ->whereHas('discussion.participants', fn ($q) => $q
                ->whereKey($userId)
                ->whereRaw('discussion_messages.id > COALESCE(discussion_user.cleared_through_id, 0)'))
            ->whereRaw("body LIKE ? ESCAPE '!'", [$motif])
            ->with(['author:id,name', 'discussion.participants:id,name'])
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->map(fn (DiscussionMessage $m) => [
                'id'         => $m->id,
                'discussion' => $m->discussion->titleFor($userId),
                'author'     => $m->author?->name ?? 'Utilisateur supprimé',
                'excerpt'    => $this->excerpt($m->body, $terme),
                'date'       => $m->created_at->locale('fr')->isoFormat('D MMM YYYY'),
                // L'ouverture se fait au bon endroit du fil, pas à la fin.
                'url'        => route('messages.show', $m->discussion) . '?at=' . $m->id,
            ])
            ->values()
            ->all();

        return response()
            ->json(['results' => $resultats])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Extrait le passage autour du terme trouvé, plutôt que le début du message.
     */
    private function excerpt(string $corps, string $terme): string
    {
        $position = mb_stripos($corps, $terme);

        if ($position === false || mb_strlen($corps) <= 120) {
            return Str::limit($corps, 120);
        }

        $debut = max(0, $position - 40);

        return ($debut > 0 ? '…' : '') . Str::limit(mb_substr($corps, $debut), 120);
    }

    /**
     * Messages plus anciens qu'un identifiant donné.
     *
     * Le fil ne charge qu'une fenêtre : sans cela, une conversation de plusieurs
     * milliers de messages serait intégralement transmise à chaque ouverture.
     */
    public function history(Request $request, Discussion $discussion): JsonResponse
    {
        $discussion->load('participants');
        $this->authorize('view', $discussion);

        $validated = $request->validate([
            'before' => ['required', 'integer', 'min:1'],
        ]);

        $messages = $discussion->visibleMessagesFor($request->user()->id)
            ->with(['author:id,name,avatar_path', 'attachments', 'reactions'])
            ->where('id', '<', $validated['before'])
            ->latest('id')
            ->limit(self::PAGE_SIZE)
            ->get()
            ->reverse()
            ->values();

        $plusAncien = $messages->first()?->id;

        return response()
            ->json([
                'messages'  => $messages->map(fn ($m) => $m->toPayload($request->user()->id))->all(),
                // Sert au client à masquer le bouton une fois le fil épuisé.
                'has_older' => $plusAncien
                    ? $discussion->visibleMessagesFor($request->user()->id)->where('id', '<', $plusAncien)->exists()
                    : false,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Fenêtre de messages à afficher à l'ouverture d'un fil.
     *
     * Sans cible : les plus récents. Avec cible : le message visé, entouré de
     * son contexte, afin qu'un résultat de recherche s'ouvre au bon endroit.
     */
    private function windowFor(Discussion $discussion, int $userId, ?int $focusedId)
    {
        $requete = $discussion->visibleMessagesFor($userId)->with(['author:id,name,avatar_path', 'attachments']);

        if (!$focusedId) {
            return $requete->latest('id')->limit(self::HISTORY_LIMIT)->get()->reverse()->values();
        }

        $avant = (clone $requete)->where('id', '<=', $focusedId)
            ->latest('id')->limit(intdiv(self::HISTORY_LIMIT, 2))->get();

        $apres = (clone $requete)->where('id', '>', $focusedId)
            ->oldest('id')->limit(intdiv(self::HISTORY_LIMIT, 2))->get();

        return $avant->reverse()->concat($apres)->values();
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

        $messages = $discussion->visibleMessagesFor($request->user()->id)
            ->with(['author:id,name,avatar_path', 'attachments'])
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
                // Les réactions portent sur des messages déjà affichés : elles
                // ne peuvent pas transiter par la liste des nouveaux messages.
                'reactions' => $this->reactionsFor($discussion, $request->user()->id),
                ...$this->revisionsFor($discussion, $request->user()->id),
                // Qui écrit en ce moment : volatile, jamais stocké en base.
                'typing' => Typing::othersFor($discussion->id, $request->user()->id),
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
                // De quoi composer une notification discrète pour le dernier
                // message reçu, sans second aller-retour.
                'latest'         => Unread::latestFor($request->user()->id),
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
     * Supprime la conversation de son côté.
     *
     * Rien n'est effacé pour les autres participants : la conversation disparaît
     * de la liste et l'historique n'est plus montré, mais un nouveau message la
     * fait réapparaître, sans ce qui précédait. C'est le comportement attendu
     * d'une messagerie, et le seul acceptable pour une conversation à deux, que
     * l'on ne peut pas retirer à l'autre.
     */
    public function clear(Request $request, Discussion $discussion)
    {
        $discussion->load('participants');
        $this->authorize('clear', $discussion);

        // withTrashed : la frontière doit couvrir un dernier message supprimé,
        // sans quoi il resterait du « bon » côté.
        $frontiere = (int) $discussion->messages()->withTrashed()->max('id');

        $discussion->participants()->updateExistingPivot($request->user()->id, [
            'cleared_through_id' => $frontiere,
            'last_read_at'       => now(),
        ]);

        return redirect()->route('messages.index')
            ->with('success', 'Conversation « ' . $discussion->titleFor($request->user()->id) . ' » supprimée.');
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
            ->whereHas('participants', fn ($q) => $q
                ->whereKey($userId)
                // Une conversation supprimée reste masquée tant qu'aucun message
                // n'est arrivé depuis.
                ->where(fn ($q) => $q
                    ->whereNull('discussion_user.cleared_through_id')
                    ->orWhereExists(fn ($s) => $s
                        ->selectRaw('1')
                        ->from('discussion_messages as dm')
                        ->whereColumn('dm.discussion_id', 'discussion_user.discussion_id')
                        ->whereColumn('dm.id', '>', 'discussion_user.cleared_through_id')
                        ->whereNull('dm.deleted_at'))))
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

    /**
     * Réactions de tous les messages d'un fil, indexées par message.
     *
     * Une seule requête sur la table des réactions, plutôt qu'un chargement de
     * tous les messages : seuls ceux qui en portent sont renvoyés, et le client
     * efface les lignes des messages absents.
     *
     * @return array<int, array>
     */
    private function reactionsFor(Discussion $discussion, int $userId): array
    {
        $messageIds = $discussion->visibleMessagesFor($userId)
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->pluck('id');

        return DiscussionMessage::whereIn('id', $messageIds)
            ->whereHas('reactions')
            ->with('reactions')
            ->get()
            ->mapWithKeys(fn (DiscussionMessage $m) => [$m->id => $m->reactionSummary($userId)])
            ->all();
    }

    /**
     * Refuse un message qui n'appartient pas au fil de l'URL.
     *
     * Sans ce contrôle, les identifiants imbriqués pourraient être mélangés
     * pour agir sur le message d'une autre conversation.
     */
    private function ensureBelongsTo(DiscussionMessage $message, Discussion $discussion): void
    {
        abort_if($message->discussion_id !== $discussion->id, 404);
    }

    /**
     * Messages modifiés et supprimés du fil, pour que le sondage puisse les
     * répercuter chez les autres participants : ni les uns ni les autres
     * n'apparaissent dans la liste des nouveaux messages.
     *
     * @return array{edited: array<int, array>, deleted: array<int, int>}
     */
    private function revisionsFor(Discussion $discussion, int $userId): array
    {
        $edited = $discussion->visibleMessagesFor($userId)
            ->whereNotNull('edited_at')
            ->with(['author:id,name,avatar_path', 'attachments', 'reactions'])
            ->latest('edited_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->mapWithKeys(fn (DiscussionMessage $m) => [$m->id => $m->toPayload($userId)])
            ->all();

        $deleted = $discussion->visibleMessagesFor($userId)
            ->onlyTrashed()
            ->latest('deleted_at')
            ->limit(self::HISTORY_LIMIT)
            ->pluck('id')
            ->all();

        return ['edited' => $edited, 'deleted' => $deleted];
    }

    private function markAsRead(Discussion $discussion, int $userId): void
    {
        $discussion->participants()->updateExistingPivot($userId, ['last_read_at' => now()]);
    }
}
