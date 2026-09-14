<?php

namespace Tests\Feature;

use App\Models\PbxServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Annuaire des centrex FreePBX : liste partagée entre tous les utilisateurs
 * connectés, mais mots de passe chiffrés au repos et servis à la demande.
 */
class PbxServerTest extends TestCase
{
    use RefreshDatabase;

    private function centrex(array $attributs = []): PbxServer
    {
        return PbxServer::create(array_merge([
            'name'      => 'Centrex Lemoine',
            'client'    => 'Lemoine SAS',
            'protocol'  => 'https',
            'host'      => '10.20.30.40',
            'port'      => 8443,
            'path'      => '/admin',
            'login'     => 'admin',
            'password'  => 'MotDePasseCentrex42',
            'is_active' => true,
        ], $attributs));
    }

    /**
     * Règle de nommage : les machines sont nommées pour l'hébergeur, pas pour
     * l'écran. Le préfixe IPBX ne distingue rien puisque tout le parc le porte.
     *
     * @dataProvider nomsDeMachines
     */
    public function test_le_nom_de_machine_devient_lisible(string $brut, string $attendu): void
    {
        $this->assertSame($attendu, PbxServer::nomLisible($brut));
    }

    public static function nomsDeMachines(): array
    {
        return [
            'préfixe retiré'          => ['IPBX_AISCAL', 'AISCAL'],
            'underscores en espaces'  => ['IPBX_ARMA-TUYAUTERIE_NEW', 'ARMA TUYAUTERIE NEW'],
            'casse indifférente'      => ['ipbx_test', 'test'],
            'tirets en espaces'       => ['IPBX_AD-SISTO-CAEN', 'AD SISTO CAEN'],
            'autre préfixe intact'    => ['VPS_CAEN-KINE-SPORT', 'VPS CAEN KINE SPORT'],
            'sans préfixe'            => ['HYDROTEK-NEW', 'HYDROTEK NEW'],
            'chiffres préservés'      => ['IPBX_ATELIER-AUTOMOBILE-61', 'ATELIER AUTOMOBILE 61'],
            // Ne jamais produire une fiche sans nom : « IPBX » seul reste tel
            // quel plutôt que de se réduire à une chaîne vide.
            'préfixe seul conservé'   => ['IPBX', 'IPBX'],
            'espaces surnuméraires'   => ['IPBX_  DOUBLE   ESPACE ', 'DOUBLE ESPACE'],
        ];
    }

    public function test_un_visiteur_anonyme_est_renvoye_vers_la_connexion(): void
    {
        $this->get(route('tools.centrex.index'))->assertRedirect('/login');
    }

    public function test_la_liste_est_partagee_entre_utilisateurs(): void
    {
        $auteur = User::factory()->create(['is_active' => true]);
        $autre  = User::factory()->create(['is_active' => true]);

        $this->centrex(['created_by' => $auteur->id]);

        $this->actingAs($autre)
            ->get(route('tools.centrex.index'))
            ->assertStatus(200)
            ->assertSee('Centrex Lemoine')
            ->assertSee('10.20.30.40:8443');
    }

    public function test_la_page_ne_contient_aucun_mot_de_passe(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->centrex();

        $this->actingAs($user)
            ->get(route('tools.centrex.index'))
            ->assertStatus(200)
            ->assertDontSee('MotDePasseCentrex42');
    }

    /**
     * L'attribut data-pbx alimente les modales « Identifiants » et
     * « Modifier ». Rendu avec @json, ses guillemets restaient bruts et
     * tronquaient l'attribut : les deux boutons ne réagissaient plus.
     */
    public function test_la_fiche_embarquee_dans_la_carte_est_du_json_valide(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->centrex([
            'name'   => 'Centrex « Guillemets » & Cie',
            'client' => "L'Atelier",
            'notes'  => "Première ligne\nSeconde ligne",
        ]);

        $html = $this->actingAs($user)->get(route('tools.centrex.index'))->getContent();

        $this->assertSame(1, preg_match('/data-pbx="([^"]*)"/', $html, $trouve));

        // Le navigateur décode les entités avant d'exposer dataset.pbx.
        $fiche = json_decode(html_entity_decode($trouve[1], ENT_QUOTES, 'UTF-8'), true);

        $this->assertIsArray($fiche, 'data-pbx doit contenir du JSON exploitable');
        $this->assertSame('Centrex « Guillemets » & Cie', $fiche['name']);
        $this->assertSame("L'Atelier", $fiche['client']);
        $this->assertTrue($fiche['hasPassword']);

        // Le mot de passe lui-même ne doit pas s'y trouver.
        $this->assertArrayNotHasKey('password', $fiche);
    }

    public function test_un_centrex_s_ajoute_et_son_mot_de_passe_est_chiffre(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('tools.centrex.store'), [
                'name'      => 'Centrex Durand',
                'client'    => 'Durand & Fils',
                'protocol'  => 'https',
                'host'      => 'pbx.durand.fr',
                'port'      => 8443,
                'path'      => 'admin', // sans « / » : normalisé côté serveur
                'login'     => 'admin',
                'password'  => 'SecretDurand2026',
                'is_active' => '1',
            ])
            ->assertRedirect(route('tools.centrex.index'));

        $centrex = PbxServer::where('name', 'Centrex Durand')->firstOrFail();

        $this->assertSame('/admin', $centrex->path);
        $this->assertSame('https://pbx.durand.fr:8443/admin', $centrex->url());
        $this->assertSame($user->id, $centrex->created_by);
        $this->assertSame('SecretDurand2026', $centrex->password);

        // En base, la colonne ne contient pas le mot de passe en clair.
        $brut = DB::table('pbx_servers')->where('id', $centrex->id)->value('password');
        $this->assertNotSame('SecretDurand2026', $brut);
        $this->assertStringNotContainsString('SecretDurand2026', $brut);
    }

    public function test_une_adresse_avec_schema_ou_chemin_est_refusee(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('tools.centrex.store'), [
                'name'     => 'Centrex douteux',
                'protocol' => 'https',
                'host'     => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('host');

        $this->assertSame(0, PbxServer::count());
    }

    public function test_un_champ_mot_de_passe_vide_conserve_l_existant(): void
    {
        $user    = User::factory()->create(['is_active' => true]);
        $centrex = $this->centrex();

        $this->actingAs($user)
            ->put(route('tools.centrex.update', $centrex), [
                'name'      => 'Centrex Lemoine (renommé)',
                'protocol'  => 'https',
                'host'      => '10.20.30.40',
                'port'      => 8443,
                'path'      => '/admin',
                'login'     => 'admin',
                'password'  => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('tools.centrex.index'));

        $centrex->refresh();

        $this->assertSame('Centrex Lemoine (renommé)', $centrex->name);
        $this->assertSame('MotDePasseCentrex42', $centrex->password);
    }

    public function test_le_mot_de_passe_s_efface_sur_demande_explicite(): void
    {
        $user    = User::factory()->create(['is_active' => true]);
        $centrex = $this->centrex();

        $this->actingAs($user)
            ->put(route('tools.centrex.update', $centrex), [
                'name'           => 'Centrex Lemoine',
                'protocol'       => 'https',
                'host'           => '10.20.30.40',
                'path'           => '/admin',
                'password'       => '',
                'clear_password' => '1',
                'is_active'      => '1',
            ]);

        $this->assertNull($centrex->refresh()->password);
    }

    public function test_les_identifiants_sont_servis_a_la_demande_sans_cache(): void
    {
        $user    = User::factory()->create(['is_active' => true]);
        $centrex = $this->centrex();

        $response = $this->actingAs($user)
            ->getJson(route('tools.centrex.secret', $centrex));

        $response->assertStatus(200)
            ->assertJson(['login' => 'admin', 'password' => 'MotDePasseCentrex42']);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_la_recherche_filtre_sur_le_client_et_l_hote(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->centrex();
        $this->centrex(['name' => 'Centrex Bernard', 'client' => 'Bernard SARL', 'host' => '10.99.0.1']);

        $this->actingAs($user)
            ->get(route('tools.centrex.index', ['q' => 'Bernard']))
            ->assertStatus(200)
            ->assertSee('Centrex Bernard')
            ->assertDontSee('Centrex Lemoine');
    }

    public function test_un_centrex_se_supprime(): void
    {
        $user    = User::factory()->create(['is_active' => true]);
        $centrex = $this->centrex();

        $this->actingAs($user)
            ->delete(route('tools.centrex.destroy', $centrex))
            ->assertRedirect(route('tools.centrex.index'));

        $this->assertSame(0, PbxServer::count());
    }
}
