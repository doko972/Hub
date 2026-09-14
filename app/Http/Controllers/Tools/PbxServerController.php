<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PbxServer;
use App\Services\OvhInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Annuaire des centrex FreePBX.
 *
 * La liste est partagée : tout utilisateur connecté la consulte et la modifie.
 * Ce qui n'est pas partagé, c'est le mot de passe dans le HTML — il n'est
 * jamais rendu dans la page, seulement servi par secret() à l'ouverture
 * explicite de la fiche d'identifiants.
 */
class PbxServerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $servers = PbxServer::query()
            ->when($q !== '', function ($query) use ($q) {
                $terme = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $query->where(function ($sub) use ($terme) {
                    $sub->where('name', 'like', $terme)
                        ->orWhere('client', 'like', $terme)
                        ->orWhere('host', 'like', $terme)
                        ->orWhere('ovh_service_name', 'like', $terme);
                });
            })
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('tools.centrex', [
            'servers'   => $servers,
            'q'         => $q,
            'protocols' => PbxServer::availableProtocols(),
            // Sans clés OVH, le bouton d'import n'a rien à proposer : autant
            // ne pas l'afficher du tout.
            'ovhPret'   => OvhInventory::isConfigured(),
            // Redémarrer coupe les communications en cours : le bouton est
            // réservé aux administrateurs, la route le revérifie.
            'peutRedemarrer' => OvhInventory::isConfigured() && $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['created_by'] = $request->user()->id;

        // Un mot de passe vide reste vide : pas de chaîne « » chiffrée pour rien.
        if (($data['password'] ?? '') === '') {
            $data['password'] = null;
        }

        $centrex = PbxServer::create($data);

        ActivityLog::record('created', 'Centrex', $centrex->id, $centrex->name);

        return redirect()
            ->route('tools.centrex.index')
            ->with('success', 'Centrex « ' . $centrex->name . ' » ajouté.');
    }

    public function update(Request $request, PbxServer $centrex)
    {
        $data = $this->validated($request);

        // Le formulaire d'édition ne reçoit jamais le mot de passe existant :
        // un champ laissé vide signifie « ne pas y toucher ». L'effacer demande
        // une action explicite.
        if ($request->boolean('clear_password')) {
            $data['password'] = null;
        } elseif (($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        $centrex->update($data);

        ActivityLog::record('updated', 'Centrex', $centrex->id, $centrex->name);

        return redirect()
            ->route('tools.centrex.index')
            ->with('success', 'Centrex « ' . $centrex->name . ' » mis à jour.');
    }

    public function destroy(PbxServer $centrex)
    {
        $nom = $centrex->name;
        $centrex->delete();

        ActivityLog::record('deleted', 'Centrex', null, $nom);

        return redirect()
            ->route('tools.centrex.index')
            ->with('success', 'Centrex « ' . $nom . ' » supprimé.');
    }

    /**
     * Identifiants d'un centrex, servis à l'ouverture de la fiche.
     * Jamais mis en cache, jamais rendus dans le HTML de la liste.
     */
    public function secret(PbxServer $centrex): JsonResponse
    {
        return response()
            ->json([
                'login'    => $centrex->login ?? '',
                'password' => $centrex->password ?? '',
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Règles communes à la création et à la modification.
     *
     * L'hôte et le chemin sont contraints par expression régulière : l'URL
     * d'accès est reconstruite à partir de ces morceaux, ils ne doivent donc
     * accepter ni espace, ni schéma, ni caractère de contrôle.
     */
    private function validated(Request $request): array
    {
        $request->merge([
            'host' => trim((string) $request->input('host')),
            'path' => trim((string) $request->input('path')),
        ]);

        // Confort de saisie : « admin » et « /admin » désignent la même page.
        if ($request->input('path') !== '' && !str_starts_with($request->input('path'), '/')) {
            $request->merge(['path' => '/' . $request->input('path')]);
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:80'],
            'client'   => ['nullable', 'string', 'max:120'],
            'protocol' => ['required', Rule::in(array_keys(PbxServer::availableProtocols()))],
            'host'     => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$/'],
            'port'     => ['nullable', 'integer', 'between:1,65535'],
            'path'     => ['nullable', 'string', 'max:120', 'regex:/^\/[^\s#]*$/'],
            'login'    => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Donnez un nom à ce centrex.',
            'host.required' => 'Indiquez l\'adresse IP ou le nom d\'hôte de la VM.',
            'host.regex'    => 'Adresse invalide : saisissez uniquement une IP ou un nom d\'hôte, sans « http:// » ni chemin.',
            'path.regex'    => 'Le chemin doit commencer par « / » et ne pas contenir d\'espace.',
            'port.between'  => 'Le port doit être compris entre 1 et 65535.',
        ]);

        // Chemin vide accepté tel quel : le bouton « Ouvrir » vise alors la
        // racine du serveur, ce que veulent les centrex dont l'interface n'est
        // pas sous /admin.
        $data['path']      = $data['path'] ?? '';
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
