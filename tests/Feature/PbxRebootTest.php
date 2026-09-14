<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PbxServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Redémarrage de la machine d'un centrex.
 *
 * C'est la seule action du Hub qui écrive chez OVH, et elle coupe les
 * communications en cours : ce qui est vérifié ici tient autant au périmètre
 * (qui a le droit, quelles fiches) qu'au fait que l'appel parte correctement.
 */
class PbxRebootTest extends TestCase
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
        ]);
    }

    /**
     * OVH simulé, qui accepte tout par défaut.
     *
     * Les stubs de Http::fake fusionnent et le premier inscrit l'emporte : une
     * surcharge doit donc remplacer la clé du défaut, pas s'ajouter derrière.
     * D'où array_merge plutôt qu'un second appel à Http::fake.
     *
     * @param  array<string, mixed>  $surcharges
     */
    private function fakeOvh(array $surcharges = []): void
    {
        Http::fake(array_merge([
            '*/auth/time' => Http::response((string) time(), 200),
            '*/reboot'    => Http::response(null, 200),
        ], $surcharges));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'admin']);
    }

    private function membre(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'user']);
    }

    private function fiche(array $attributs = []): PbxServer
    {
        return PbxServer::create(array_merge([
            'name'             => 'Centrex Durand',
            'protocol'         => 'http',
            'host'             => '51.75.10.20',
            'path'             => '',
            'ovh_service_name' => 'inst-aaa',
            'ovh_project'      => 'projet-ipbx',
        ], $attributs));
    }

    public function test_un_visiteur_anonyme_ne_redemarre_rien(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche();

        $this->postJson(route('tools.centrex.reboot', $centrex))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_un_utilisateur_non_admin_se_voit_refuser_l_action(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche();

        $this->actingAs($this->membre())
            ->postJson(route('tools.centrex.reboot', $centrex))
            ->assertForbidden();

        // Le refus doit intervenir avant tout appel : rien ne part chez OVH.
        Http::assertNothingSent();
    }

    public function test_un_admin_declenche_un_redemarrage_doux_de_l_instance(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche();

        $this->actingAs($this->admin())
            ->postJson(route('tools.centrex.reboot', $centrex))
            ->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Centrex Durand'));

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/cloud/project/projet-ipbx/instance/inst-aaa/reboot')
                && $request->body() === '{"type":"soft"}';
        });
    }

    public function test_la_signature_couvre_le_corps_envoye(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche();

        $this->actingAs($this->admin())->postJson(route('tools.centrex.reboot', $centrex));

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }

            $horodate = $request->header('X-Ovh-Timestamp')[0] ?? '';

            // Le corps fait partie de la signature : si l'un des deux change
            // sans l'autre, OVH rejette. C'est précisément ce qu'on fige ici.
            $attendue = '$1$' . sha1(implode('+', [
                'secret-application',
                'cle-consommateur',
                'POST',
                $request->url(),
                $request->body(),
                $horodate,
            ]));

            return ($request->header('X-Ovh-Signature')[0] ?? '') === $attendue;
        });
    }

    public function test_un_vps_bare_metal_utilise_son_propre_endpoint(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche([
            'ovh_service_name' => 'vps-ccc.vps.ovh.net',
            'ovh_project'      => null,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('tools.centrex.reboot', $centrex))
            ->assertOk();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/vps/vps-ccc.vps.ovh.net/reboot'));
    }

    public function test_une_fiche_sans_machine_ovh_ne_declenche_aucun_appel(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche(['ovh_service_name' => null, 'ovh_project' => null]);

        $this->actingAs($this->admin())
            ->postJson(route('tools.centrex.reboot', $centrex))
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_le_redemarrage_est_trace_dans_le_journal(): void
    {
        $this->fakeOvh();

        $centrex = $this->fiche();
        $admin   = $this->admin();

        $this->actingAs($admin)->postJson(route('tools.centrex.reboot', $centrex))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'      => $admin->id,
            'action'       => 'rebooted',
            'subject_type' => 'Centrex',
            'subject_id'   => $centrex->id,
        ]);
    }

    public function test_un_droit_manquant_sur_le_jeton_donne_un_message_explicite(): void
    {
        $this->fakeOvh([
            '*/reboot' => Http::response(['message' => 'This call has not been granted'], 403),
        ]);

        $centrex = $this->fiche();

        $this->actingAs($this->admin())
            ->postJson(route('tools.centrex.reboot', $centrex))
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'ce droit manque au jeton'));

        // Tracé avant l'appel : un redémarrage refusé laisse tout de même une
        // trace, car on ne sait pas toujours si OVH a agi avant de répondre.
        $this->assertSame(1, ActivityLog::where('action', 'rebooted')->count());
    }

    public function test_le_bouton_n_apparait_pas_pour_un_non_admin(): void
    {
        $this->fiche();

        $this->actingAs($this->membre())
            ->get(route('tools.centrex.index'))
            ->assertOk()
            ->assertDontSee('data-pbx-reboot', false);

        $this->actingAs($this->admin())
            ->get(route('tools.centrex.index'))
            ->assertOk()
            ->assertSee('data-pbx-reboot', false);
    }
}
