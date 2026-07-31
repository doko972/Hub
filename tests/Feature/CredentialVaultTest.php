<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolFamily;
use App\Models\User;
use App\Models\UserToolCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le coffre d'identifiants ne doit jamais être rendu dans le HTML : il est
 * servi à la demande, uniquement à son propriétaire.
 */
class CredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    private function tool(): Tool
    {
        $family = ToolFamily::create([
            'name'       => 'Bureautique',
            'is_active'  => true,
            'sort_order' => 1,
        ]);

        return Tool::create([
            'title'          => 'Intranet',
            'url'            => 'https://intranet.example.com',
            'tool_family_id' => $family->id,
            'is_active'      => true,
            'is_public'      => true,
            'sort_order'     => 1,
        ]);
    }

    public function test_le_tableau_de_bord_ne_contient_aucun_mot_de_passe(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        UserToolCredential::create([
            'user_id'  => $user->id,
            'tool_id'  => $tool->id,
            'login'    => 'jean.dupont',
            'password' => 'MotDePasseTresSecret42',
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('MotDePasseTresSecret42');
        $response->assertDontSee('jean.dupont');
        // Le bouton signale seulement la présence d'identifiants.
        $response->assertSee('data-has-credentials="1"', false);
    }

    public function test_les_identifiants_sont_servis_a_la_demande_a_leur_proprietaire(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        UserToolCredential::create([
            'user_id'  => $user->id,
            'tool_id'  => $tool->id,
            'login'    => 'jean.dupont',
            'password' => 'MotDePasseTresSecret42',
        ]);

        $response = $this->actingAs($user)->getJson("/credentials/{$tool->id}");

        $response->assertStatus(200)
            ->assertJson([
                'login'    => 'jean.dupont',
                'password' => 'MotDePasseTresSecret42',
            ]);

        // La réponse ne doit pas finir dans un cache navigateur ou proxy.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_un_utilisateur_ne_voit_pas_les_identifiants_d_un_autre(): void
    {
        $proprietaire = User::factory()->create(['is_active' => true]);
        $intrus       = User::factory()->create(['is_active' => true]);
        $tool         = $this->tool();

        UserToolCredential::create([
            'user_id'  => $proprietaire->id,
            'tool_id'  => $tool->id,
            'login'    => 'jean.dupont',
            'password' => 'MotDePasseTresSecret42',
        ]);

        $this->actingAs($intrus)
            ->getJson("/credentials/{$tool->id}")
            ->assertStatus(200)
            ->assertJson(['login' => '', 'password' => '']);
    }

    public function test_un_visiteur_anonyme_ne_peut_pas_lire_les_identifiants(): void
    {
        $tool = $this->tool();

        $this->get("/credentials/{$tool->id}")->assertRedirect('/login');
    }
}
