<?php

namespace Tests\Feature;

use App\Models\PbxServer;
use App\Models\User;
use App\Services\OvhInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Import des centrex depuis l'inventaire OVHcloud.
 *
 * L'API OVH est simulée. Ce qui est vérifié ici : la signature des requêtes,
 * la fusion des deux sources (instances Public Cloud et VPS Bare Metal), le
 * repérage des machines déjà référencées, le fait qu'une source en échec ne
 * fasse pas disparaître l'autre en silence, et que l'import ne fasse confiance
 * qu'à la référence envoyée — jamais au nom ni à l'IP.
 */
class PbxOvhImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ovh.endpoint'           => 'ovh-eu',
            'services.ovh.application_key'    => 'cle-application',
            'services.ovh.application_secret' => 'secret-application',
            'services.ovh.consumer_key'       => 'cle-consommateur',
            'services.ovh.cloud_projects'     => '',
            'services.centrex.default_login'    => null,
            'services.centrex.default_password' => null,
        ]);
    }

    /**
     * Compte OVH factice : un projet Public Cloud de deux instances (dont une
     * sans IP publique) et un VPS Bare Metal.
     */
    private function fakeOvh(array $surcharges = []): void
    {
        Http::fake(array_merge([
            '*/auth/time' => Http::response((string) time(), 200),

            '*/cloud/project' => Http::response(['projet-ipbx'], 200),

            '*/cloud/project/projet-ipbx' => Http::response([
                'project_id' => 'projet-ipbx', 'description' => 'IPBX-GENERAL', 'status' => 'ok',
            ], 200),

            '*/cloud/project/projet-ipbx/instance' => Http::response([
                [
                    'id' => 'inst-aaa', 'name' => 'ipbx-durand', 'status' => 'ACTIVE', 'region' => 'GRA11',
                    'ipAddresses' => [
                        ['ip' => '10.0.0.5',      'type' => 'private', 'version' => 4],
                        ['ip' => '51.75.10.20',   'type' => 'public',  'version' => 4],
                        ['ip' => '2001:41d0:1::', 'type' => 'public',  'version' => 6],
                    ],
                ],
                [
                    'id' => 'inst-bbb', 'name' => 'ipbx-interne', 'status' => 'SHUTOFF', 'region' => 'SBG5',
                    'ipAddresses' => [
                        ['ip' => '10.0.0.6', 'type' => 'private', 'version' => 4],
                    ],
                ],
            ], 200),

            '*/1.0/vps' => Http::response(['vps-ccc.vps.ovh.net'], 200),
            '*/vps/vps-ccc.vps.ovh.net/ips' => Http::response(['141.94.1.2'], 200),
            '*/vps/vps-ccc.vps.ovh.net' => Http::response([
                'name' => 'vps-ccc.vps.ovh.net', 'displayName' => 'Vieux centrex',
                'state' => 'running', 'zone' => 'gra',
            ], 200),
        ], $surcharges));
    }

    private function utilisateur(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array>
     */
    private function inventaire(): \Illuminate\Support\Collection
    {
        $reponse = $this->actingAs($this->utilisateur())
            ->getJson(route('tools.centrex.ovh.list'))
            ->assertOk();

        return collect($reponse->json('machines'))->keyBy('ref');
    }

    public function test_un_visiteur_anonyme_n_atteint_pas_l_inventaire(): void
    {
        $this->getJson(route('tools.centrex.ovh.list'))->assertUnauthorized();
    }

    public function test_les_instances_public_cloud_sont_listees_avec_leur_ip_publique(): void
    {
        $this->fakeOvh();

        $machines = $this->inventaire();

        $this->assertSame('ipbx-durand', $machines['inst-aaa']['name']);
        $this->assertSame('IPBX-GENERAL', $machines['inst-aaa']['group']);
        $this->assertSame('GRA11', $machines['inst-aaa']['zone']);

        // L'IP privée de vRack ne doit pas être retenue : une fiche centrex
        // pointant dessus serait injoignable depuis le Hub.
        $this->assertSame('51.75.10.20', $machines['inst-aaa']['ipv4']);
        $this->assertSame('2001:41d0:1::', $machines['inst-aaa']['ipv6']);
        $this->assertNull($machines['inst-bbb']['ipv4']);
    }

    public function test_les_vps_bare_metal_sont_fusionnes_avec_les_instances(): void
    {
        $this->fakeOvh();

        $machines = $this->inventaire();

        $this->assertCount(3, $machines);
        $this->assertSame('instance', $machines['inst-aaa']['kind']);
        $this->assertSame('vps', $machines['vps-ccc.vps.ovh.net']['kind']);
        $this->assertSame('141.94.1.2', $machines['vps-ccc.vps.ovh.net']['ipv4']);
    }

    public function test_une_machine_deja_saisie_a_la_main_est_reperee_par_son_ip(): void
    {
        $this->fakeOvh();

        PbxServer::create([
            'name' => 'Déjà là', 'protocol' => 'https', 'host' => '51.75.10.20', 'path' => '/admin',
        ]);

        $this->assertTrue($this->inventaire()['inst-aaa']['existing']);
    }

    public function test_les_requetes_partent_signees(): void
    {
        $this->fakeOvh();
        $this->inventaire();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/cloud/project')) {
                return false;
            }

            $horodate = $request->header('X-Ovh-Timestamp')[0] ?? '';

            $attendue = '$1$' . sha1(implode('+', [
                'secret-application',
                'cle-consommateur',
                'GET',
                $request->url(),
                '',
                $horodate,
            ]));

            return ($request->header('X-Ovh-Application')[0] ?? '') === 'cle-application'
                && ($request->header('X-Ovh-Consumer')[0] ?? '') === 'cle-consommateur'
                && ($request->header('X-Ovh-Signature')[0] ?? '') === $attendue;
        });
    }

    public function test_l_import_cree_une_fiche_par_machine_cochee(): void
    {
        $this->fakeOvh();

        $this->actingAs($this->utilisateur())
            ->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-aaa']])
            ->assertRedirect(route('tools.centrex.index'));

        $centrex = PbxServer::firstWhere('ovh_service_name', 'inst-aaa');

        $this->assertNotNull($centrex);
        $this->assertSame('ipbx-durand', $centrex->name);
        $this->assertSame('51.75.10.20', $centrex->host);
        $this->assertSame('http', $centrex->protocol);
        // Aucun chemin imposé par l'import : « Ouvrir » vise la racine.
        $this->assertSame('', $centrex->path);
        $this->assertTrue($centrex->is_active);
        $this->assertStringContainsString('IPBX-GENERAL', $centrex->notes);
        $this->assertStringContainsString('GRA11', $centrex->notes);
    }

    public function test_les_identifiants_par_defaut_sont_poses_sur_les_fiches_importees(): void
    {
        config([
            'services.centrex.default_login'    => 'admin',
            'services.centrex.default_password' => 'MotDePasseUsine42',
        ]);
        $this->fakeOvh();

        $this->actingAs($this->utilisateur())
            ->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-aaa']]);

        $centrex = PbxServer::firstWhere('ovh_service_name', 'inst-aaa');

        $this->assertSame('admin', $centrex->login);
        $this->assertSame('MotDePasseUsine42', $centrex->password);

        // Chiffré au repos : un dump de la base ne doit pas livrer le mot de
        // passe d'usine de l'ensemble du parc.
        $brut = \Illuminate\Support\Facades\DB::table('pbx_servers')
            ->where('id', $centrex->id)->value('password');

        $this->assertNotSame('MotDePasseUsine42', $brut);
    }

    public function test_sans_identifiants_par_defaut_les_fiches_restent_vierges(): void
    {
        $this->fakeOvh();

        $this->actingAs($this->utilisateur())
            ->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-aaa']]);

        $centrex = PbxServer::firstWhere('ovh_service_name', 'inst-aaa');

        // Ni chaîne vide chiffrée, ni identifiant fantôme dans la fiche.
        $this->assertNull($centrex->login);
        $this->assertNull($centrex->password);
    }

    public function test_une_machine_a_l_arret_donne_une_fiche_inactive(): void
    {
        // Même instance, mais éteinte et pourvue d'une IP publique.
        $this->fakeOvh([
            '*/cloud/project/projet-ipbx/instance' => Http::response([[
                'id' => 'inst-ddd', 'name' => 'ipbx-eteint', 'status' => 'SHUTOFF', 'region' => 'GRA11',
                'ipAddresses' => [['ip' => '51.75.99.99', 'type' => 'public', 'version' => 4]],
            ]], 200),
        ]);

        $this->actingAs($this->utilisateur())
            ->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-ddd']]);

        $this->assertFalse(PbxServer::firstWhere('ovh_service_name', 'inst-ddd')->is_active);
    }

    public function test_une_machine_deja_importee_n_est_pas_dupliquee(): void
    {
        $this->fakeOvh();
        $utilisateur = $this->utilisateur();

        $this->actingAs($utilisateur)->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-aaa']]);
        $this->actingAs($utilisateur)->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-aaa']]);

        $this->assertSame(1, PbxServer::where('ovh_service_name', 'inst-aaa')->count());
    }

    public function test_une_machine_sans_ip_publique_n_est_pas_importee(): void
    {
        $this->fakeOvh();

        $this->actingAs($this->utilisateur())
            ->post(route('tools.centrex.ovh.import'), ['machines' => ['inst-bbb']]);

        $this->assertSame(0, PbxServer::count());
    }

    public function test_une_reference_inconnue_de_l_inventaire_est_refusee(): void
    {
        $this->fakeOvh();

        // Le nom et l'IP ne sont jamais lus depuis le formulaire : une
        // référence absente de l'inventaire ne crée rien, quoi qu'on envoie.
        $this->actingAs($this->utilisateur())
            ->post(route('tools.centrex.ovh.import'), [
                'machines' => ['inst-pirate'],
                'host'     => '10.0.0.1',
                'name'     => 'Fiche forgée',
            ]);

        $this->assertSame(0, PbxServer::count());
    }

    public function test_une_source_en_echec_n_emporte_pas_l_autre_et_se_signale(): void
    {
        // Jeton sans droit sur les VPS : les instances doivent tout de même
        // remonter, et le manque doit être annoncé.
        $this->fakeOvh([
            '*/1.0/vps' => Http::response(['message' => 'This call has not been granted'], 403),
        ]);

        $reponse = $this->actingAs($this->utilisateur())
            ->getJson(route('tools.centrex.ovh.list'))
            ->assertOk();

        $this->assertCount(2, $reponse->json('machines'));
        $this->assertNotEmpty($reponse->json('notes'));
        $this->assertStringContainsString('VPS', $reponse->json('notes.0'));
    }

    public function test_une_liste_tronquee_par_ovh_est_signalee(): void
    {
        // OVH annonce 200 instances mais n'en renvoie qu'une : le décalage doit
        // remonter à l'écran, c'est exactement le cas qu'on ne veut pas taire.
        $this->fakeOvh([
            '*/cloud/project/projet-ipbx/instance' => Http::response([[
                'id' => 'inst-aaa', 'name' => 'ipbx-durand', 'status' => 'ACTIVE', 'region' => 'GRA11',
                'ipAddresses' => [['ip' => '51.75.10.20', 'type' => 'public', 'version' => 4]],
            ]], 200, ['X-Pagination-Elements' => '200']),
        ]);

        $notes = $this->actingAs($this->utilisateur())
            ->getJson(route('tools.centrex.ovh.list'))
            ->assertOk()
            ->json('notes');

        $this->assertNotEmpty($notes);
        $this->assertStringContainsString('200', implode(' ', $notes));
    }

    public function test_l_import_peut_etre_restreint_a_certains_projets(): void
    {
        config(['services.ovh.cloud_projects' => 'un-autre-projet']);
        $this->fakeOvh();

        // Seuls les VPS restent : le projet du compte n'est pas dans la liste.
        $machines = $this->inventaire();

        $this->assertCount(1, $machines);
        $this->assertSame('vps', $machines->first()['kind']);
    }

    public function test_sans_cles_ovh_la_page_ne_propose_pas_l_import(): void
    {
        config(['services.ovh.application_key' => null]);

        $this->assertFalse(OvhInventory::isConfigured());

        $this->actingAs($this->utilisateur())
            ->get(route('tools.centrex.index'))
            ->assertOk()
            ->assertDontSee('Importer depuis OVH');
    }

    public function test_une_api_ovh_totalement_muette_donne_un_message_et_non_une_page_blanche(): void
    {
        Http::fake([
            '*/auth/time'     => Http::response((string) time(), 200),
            '*/cloud/project' => Http::response(['message' => 'Invalid signature'], 403),
            '*/1.0/vps'       => Http::response(['message' => 'Invalid signature'], 403),
        ]);

        $this->actingAs($this->utilisateur())
            ->getJson(route('tools.centrex.ovh.list'))
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ce droit manque au jeton'));
    }
}
