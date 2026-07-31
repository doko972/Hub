<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolFamily;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le tableau de bord combine deux notions distinctes :
 *   — les droits d'accès (ce que l'on a le droit de voir) ;
 *   — la préférence d'affichage (ce que l'on souhaite voir).
 *
 * Les confondre a produit le bug corrigé ici : la préférence d'un
 * administrateur était ignorée.
 */
class DashboardPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private ToolFamily $famille;

    protected function setUp(): void
    {
        parent::setUp();

        $this->famille = ToolFamily::create([
            'name'       => 'Bureautique',
            'is_active'  => true,
            'sort_order' => 1,
        ]);
    }

    private function tool(string $titre, bool $public = true): Tool
    {
        return Tool::create([
            'title'          => $titre,
            'url'            => 'https://exemple.fr/' . strtolower($titre),
            'tool_family_id' => $this->famille->id,
            'is_active'      => true,
            'is_public'      => $public,
            'sort_order'     => 1,
        ]);
    }

    public function test_un_administrateur_voit_sa_selection_et_elle_seule(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $garde  = $this->tool('Intranet');
        $ecarte = $this->tool('Facturation');

        $admin->selectedTools()->sync([$garde->id]);

        $this->actingAs($admin)->get('/')
            ->assertStatus(200)
            ->assertSee('Intranet')
            ->assertDontSee('Facturation');
    }

    public function test_un_administrateur_sans_preference_voit_tout(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->tool('Intranet');
        $this->tool('Facturation');

        $this->actingAs($admin)->get('/')
            ->assertStatus(200)
            ->assertSee('Intranet')
            ->assertSee('Facturation');
    }

    public function test_un_utilisateur_voit_sa_selection_et_elle_seule(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'user']);

        $garde  = $this->tool('Intranet');
        $ecarte = $this->tool('Facturation');

        $user->selectedTools()->sync([$garde->id]);

        $this->actingAs($user)->get('/')
            ->assertStatus(200)
            ->assertSee('Intranet')
            ->assertDontSee('Facturation');
    }

    public function test_la_preference_n_octroie_aucun_droit_supplementaire(): void
    {
        $user = User::factory()->create(['is_active' => true, 'role' => 'user']);

        $autorise = $this->tool('Intranet', public: false);
        $interdit = $this->tool('Comptabilité', public: false);

        // L'utilisateur n'est assigné qu'au premier…
        $user->tools()->sync([$autorise->id]);
        // …mais sélectionne les deux dans ses préférences.
        $user->selectedTools()->sync([$autorise->id, $interdit->id]);

        $this->actingAs($user)->get('/')
            ->assertStatus(200)
            ->assertSee('Intranet')
            ->assertDontSee('Comptabilité');
    }

    public function test_l_enregistrement_des_preferences_est_pris_en_compte(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $garde  = $this->tool('Intranet');
        $ecarte = $this->tool('Facturation');

        // Parcours complet : on passe par le formulaire, pas par le modèle.
        $this->actingAs($admin)
            ->post(route('preferences.update'), ['tools' => [$garde->id]])
            ->assertRedirect(route('preferences.edit'));

        $this->actingAs($admin)->get('/')
            ->assertSee('Intranet')
            ->assertDontSee('Facturation');
    }
}
