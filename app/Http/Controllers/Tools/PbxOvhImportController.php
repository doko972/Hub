<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PbxServer;
use App\Services\OvhInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Import des centrex depuis l'inventaire OVHcloud (instances Public Cloud et
 * VPS Bare Metal).
 *
 * Le navigateur ne parle jamais à OVH : il demande la liste au Hub, coche ce
 * qu'il veut, et renvoie des références de machine. Les noms et adresses
 * affichés ne sont pas relus à l'import — seule la référence est acceptée, le
 * reste est repris de l'inventaire côté serveur. Sans cela, un formulaire
 * trafiqué ferait créer une fiche pointant n'importe où.
 */
class PbxOvhImportController extends Controller
{
    public function __construct(private readonly OvhInventory $ovh)
    {
    }

    /**
     * Inventaire OVH, annoté de ce qui est déjà référencé.
     */
    public function list(Request $request): JsonResponse
    {
        if (! OvhInventory::isConfigured()) {
            return response()->json([
                'message' => "L'accès à l'API OVHcloud n'est pas configuré sur ce serveur.",
            ], 503);
        }

        try {
            $inventaire = $this->ovh->machines($request->boolean('refresh'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'machines' => $this->annoter($inventaire['machines']),
            // Sources ou projets en échec : affichés dans la modale plutôt que
            // passés sous silence, un inventaire incomplet devant se voir.
            'notes'    => $inventaire['notes'],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Crée une fiche par machine cochée.
     */
    public function import(Request $request)
    {
        if (! OvhInventory::isConfigured()) {
            abort(404);
        }

        $request->validate([
            'machines'   => ['required', 'array', 'max:500'],
            'machines.*' => ['string', 'max:190'],
        ], [
            'machines.required' => 'Sélectionnez au moins une machine à importer.',
        ]);

        try {
            $inventaire = $this->ovh->machines();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Inventaire indexé : ce qui n'y figure pas n'est pas importable, quoi
        // qu'en dise le formulaire.
        $parRef = collect($inventaire['machines'])->keyBy('ref');
        $dejaLa = $this->referencesExistantes();

        $cree   = 0;
        $ignore = 0;

        foreach ($request->input('machines') as $ref) {
            $machine = $parRef->get($ref);

            // Déjà référencée, inconnue d'OVH, ou sans IPv4 publique.
            if (! $machine || ! $machine['ipv4'] || $dejaLa->contains($ref)) {
                $ignore++;
                continue;
            }

            $centrex = PbxServer::create([
                'name'             => mb_substr($machine['name'], 0, 80),
                'protocol'         => 'http',
                'host'             => $machine['ipv4'],
                'ovh_service_name' => $machine['ref'],
                // Le projet est requis pour agir sur l'instance ensuite ;
                // vide pour un VPS Bare Metal, qui n'en a pas.
                'ovh_project'      => $machine['project'] ?? null,
                // Pas de chemin imposé : le bouton « Ouvrir » vise la racine,
                // à compléter à la main si l'interface est ailleurs.
                'path'             => '',
                // L'état OVH décrit la machine, pas le centrex : une machine à
                // l'arrêt donne une fiche inactive, à réactiver à la main.
                'is_active'        => $this->enMarche($machine),
                'notes'            => $this->notes($machine),
                'created_by'       => $request->user()->id,
                // Identifiants d'usine communs à toutes les machines. Copiés
                // dans la fiche : le jour où l'un d'eux change sur un centrex,
                // la fiche se corrige seule, sans toucher aux 287 autres.
                'login'            => $this->defaut('default_login'),
                'password'         => $this->defaut('default_password'),
            ]);

            ActivityLog::record('created', 'Centrex', $centrex->id, $centrex->name);

            $dejaLa->push($ref);
            $cree++;
        }

        return redirect()
            ->route('tools.centrex.index')
            ->with('success', $this->bilan($cree, $ignore));
    }

    /**
     * Marque les machines déjà présentes dans l'annuaire.
     *
     * Deux façons de la reconnaître : sa référence OVH (posée par un import
     * précédent) ou son IP (fiche saisie à la main avant l'import).
     *
     * @param  array<int, array<string, mixed>>  $machines
     * @return array<int, array<string, mixed>>
     */
    private function annoter(array $machines): array
    {
        $refs  = $this->referencesExistantes();
        $hotes = PbxServer::query()->pluck('host')->map(fn ($h) => mb_strtolower($h));

        return array_map(function (array $machine) use ($refs, $hotes) {
            $machine['existing'] = $refs->contains($machine['ref'])
                || ($machine['ipv4'] && $hotes->contains(mb_strtolower($machine['ipv4'])));

            return $machine;
        }, $machines);
    }

    /**
     * Références OVH déjà rattachées à une fiche.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function referencesExistantes()
    {
        return PbxServer::query()
            ->whereNotNull('ovh_service_name')
            ->pluck('ovh_service_name');
    }

    /**
     * Identifiant par défaut, ou null s'il n'est pas configuré.
     *
     * Une chaîne vide ne doit pas devenir un mot de passe chiffré « » : la
     * fiche afficherait un identifiant qui n'en est pas un.
     */
    private function defaut(string $cle): ?string
    {
        $valeur = (string) config('services.centrex.' . $cle, '');

        return $valeur === '' ? null : $valeur;
    }

    /**
     * Public Cloud dit « ACTIVE », les VPS disent « running ».
     *
     * @param  array<string, mixed>  $machine
     */
    private function enMarche(array $machine): bool
    {
        return in_array(mb_strtolower((string) ($machine['state'] ?? '')), ['active', 'running'], true);
    }

    /**
     * Repères techniques repris d'OVH : ils évitent d'avoir à rouvrir le
     * manager pour savoir de quelle machine vient la fiche.
     *
     * @param  array<string, mixed>  $machine
     */
    private function notes(array $machine): string
    {
        $lignes = ['Importé depuis OVHcloud le ' . now()->format('d/m/Y') . '.'];

        $lignes[] = ($machine['kind'] === 'instance' ? 'Instance : ' : 'VPS : ') . $machine['ref'];

        if ($machine['group']) {
            $lignes[] = 'Projet : ' . $machine['group'];
        }

        if ($machine['zone']) {
            $lignes[] = 'Région : ' . $machine['zone'];
        }

        if ($machine['ipv6']) {
            $lignes[] = 'IPv6 : ' . $machine['ipv6'];
        }

        return implode("\n", $lignes);
    }

    private function bilan(int $cree, int $ignore): string
    {
        if ($cree === 0) {
            return 'Aucun centrex importé : les machines sélectionnées sont déjà référencées ou sans IPv4 publique.';
        }

        $message = $cree === 1
            ? '1 centrex importé depuis OVHcloud.'
            : $cree . ' centrex importés depuis OVHcloud.';

        if ($ignore > 0) {
            $message .= ' ' . $ignore . ' machine(s) ignorée(s) : déjà référencée(s) ou sans IPv4 publique.';
        }

        return $message;
    }
}
