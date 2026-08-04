<?php

namespace Tests\Feature;

use App\Models\QrPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QrPresetTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $motDePasse = 'SecretSip2024'): array
    {
        return [
            'version'  => 1,
            'tab'      => 'sip',
            'subTab'   => 'url',
            'sip'      => [
                'sip-username'       => '1002',
                'sip-password'       => $motDePasse,
                'sip-domain'         => '51.91.145.39',
                'sip-admin-password' => 'AdminTelephone2024',
            ],
            'free'     => ['qr-url' => 'https://exemple.fr'],
            'contacts' => [['name' => 'Jean', 'phone' => '0102030405', 'blf' => true]],
        ];
    }

    public function test_une_configuration_s_enregistre_et_se_recharge(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('tools.qr-code.presets.store'), [
                'name'    => 'Poste accueil',
                'payload' => $this->payload(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('preset.name', 'Poste accueil');

        $preset = QrPreset::firstOrFail();

        $this->actingAs($user)
            ->getJson(route('tools.qr-code.presets.show', $preset))
            ->assertStatus(200)
            ->assertJsonPath('name', 'Poste accueil')
            ->assertJsonPath('payload.sip.sip-username', '1002')
            ->assertJsonPath('payload.sip.sip-password', 'SecretSip2024')
            ->assertJsonPath('payload.contacts.0.name', 'Jean');
    }

    public function test_les_mots_de_passe_sip_sont_chiffres_en_base(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->postJson(route('tools.qr-code.presets.store'), [
            'name'    => 'Poste accueil',
            'payload' => $this->payload(),
        ]);

        $brut = DB::table('qr_presets')->value('payload');

        // Un dump de la base ne doit rien livrer d'exploitable.
        $this->assertStringNotContainsString('SecretSip2024', $brut);
        $this->assertStringNotContainsString('AdminTelephone2024', $brut);
        $this->assertStringNotContainsString('51.91.145.39', $brut);

        // …mais le modèle sait toujours les relire.
        $this->assertSame('SecretSip2024', QrPreset::firstOrFail()->payload['sip']['sip-password']);
    }

    public function test_la_page_ne_contient_aucun_mot_de_passe(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $user->qrPresets()->create(['name' => 'Poste accueil', 'payload' => $this->payload()]);

        // Seuls les noms sont rendus ; la charge utile vient d'un appel séparé.
        $this->actingAs($user)->get(route('tools.qr-code'))
            ->assertStatus(200)
            ->assertSee('Poste accueil')
            ->assertDontSee('SecretSip2024')
            ->assertDontSee('AdminTelephone2024');
    }

    public function test_enregistrer_sous_un_nom_existant_met_a_jour(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->postJson(route('tools.qr-code.presets.store'), [
            'name'    => 'Poste accueil',
            'payload' => $this->payload('AncienMotDePasse'),
        ])->assertStatus(201);

        $this->actingAs($user)->postJson(route('tools.qr-code.presets.store'), [
            'name'    => 'Poste accueil',
            'payload' => $this->payload('NouveauMotDePasse'),
        ])->assertStatus(200)->assertJsonPath('updated', true);

        $this->assertDatabaseCount('qr_presets', 1);
        $this->assertSame('NouveauMotDePasse', QrPreset::firstOrFail()->payload['sip']['sip-password']);
    }

    public function test_la_configuration_d_un_autre_est_introuvable(): void
    {
        $proprietaire = User::factory()->create(['is_active' => true]);
        $intrus       = User::factory()->create(['is_active' => true]);

        $preset = $proprietaire->qrPresets()->create([
            'name'    => 'Confidentiel',
            'payload' => $this->payload(),
        ]);

        // 404 et non 403 : l'existence même ne doit pas être révélée.
        $this->actingAs($intrus)
            ->getJson(route('tools.qr-code.presets.show', $preset))
            ->assertNotFound();

        $this->actingAs($intrus)
            ->deleteJson(route('tools.qr-code.presets.destroy', $preset))
            ->assertNotFound();

        $this->assertDatabaseCount('qr_presets', 1);
    }

    public function test_chacun_ne_voit_que_ses_configurations(): void
    {
        $alice = User::factory()->create(['is_active' => true]);
        $bob   = User::factory()->create(['is_active' => true]);

        $alice->qrPresets()->create(['name' => 'Config Alice', 'payload' => $this->payload()]);
        $bob->qrPresets()->create(['name' => 'Config Bob', 'payload' => $this->payload()]);

        $this->actingAs($alice)->get(route('tools.qr-code'))
            ->assertSee('Config Alice')
            ->assertDontSee('Config Bob');
    }

    public function test_une_configuration_se_supprime(): void
    {
        $user   = User::factory()->create(['is_active' => true]);
        $preset = $user->qrPresets()->create(['name' => 'À jeter', 'payload' => $this->payload()]);

        $this->actingAs($user)
            ->deleteJson(route('tools.qr-code.presets.destroy', $preset))
            ->assertStatus(200);

        $this->assertDatabaseCount('qr_presets', 0);
    }

    public function test_le_nombre_de_configurations_est_plafonne(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        for ($i = 1; $i <= QrPreset::MAX_PER_USER; $i++) {
            $user->qrPresets()->create(['name' => "Config {$i}", 'payload' => $this->payload()]);
        }

        $this->actingAs($user)->postJson(route('tools.qr-code.presets.store'), [
            'name'    => 'Celle de trop',
            'payload' => $this->payload(),
        ])->assertStatus(422);

        // Mais une mise à jour d'une configuration existante reste possible.
        $this->actingAs($user)->postJson(route('tools.qr-code.presets.store'), [
            'name'    => 'Config 1',
            'payload' => $this->payload('Modifie'),
        ])->assertStatus(200);
    }

    public function test_un_visiteur_anonyme_n_accede_a_rien(): void
    {
        $this->get(route('tools.qr-code'))->assertRedirect('/login');
        $this->post(route('tools.qr-code.presets.store'))->assertRedirect('/login');
    }
}
