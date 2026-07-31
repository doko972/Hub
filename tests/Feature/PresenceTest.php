<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_requete_met_a_jour_la_derniere_activite(): void
    {
        $user = User::factory()->create(['is_active' => true, 'last_seen_at' => null]);

        $this->actingAs($user)->get('/')->assertStatus(200);

        $this->assertNotNull($user->fresh()->last_seen_at);
        $this->assertTrue($user->fresh()->isOnline());
    }

    public function test_l_ecriture_est_limitee_a_une_par_minute(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/');
        $premier = $user->fresh()->last_seen_at;

        // Quelques secondes plus tard : aucune écriture supplémentaire.
        $this->travel(10)->seconds();
        $this->actingAs($user)->get('/');
        $this->assertEquals($premier, $user->fresh()->last_seen_at);

        // Passé la minute, la présence est de nouveau enregistrée.
        $this->travel(2)->minutes();
        $this->actingAs($user)->get('/');
        $this->assertTrue($user->fresh()->last_seen_at->gt($premier));
    }

    public function test_un_visiteur_anonyme_ne_declenche_aucune_ecriture(): void
    {
        $this->get('/login')->assertStatus(200);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_la_liste_distingue_en_ligne_et_hors_ligne(): void
    {
        $moi     = User::factory()->create(['name' => 'Moi', 'is_active' => true, 'last_seen_at' => now()]);
        $enLigne = User::factory()->create(['name' => 'Sophie', 'is_active' => true, 'last_seen_at' => now()->subMinute()]);
        $absente = User::factory()->create(['name' => 'Anne', 'is_active' => true, 'last_seen_at' => now()->subMinutes(30)]);

        $roster = Presence::roster($moi->id)->keyBy('name');

        $this->assertTrue($roster['Moi']['is_online']);
        $this->assertTrue($roster['Moi']['is_self']);
        $this->assertTrue($roster['Sophie']['is_online']);
        $this->assertFalse($roster['Sophie']['is_self']);
        $this->assertFalse($roster['Anne']['is_online']);
        $this->assertStringContainsString('30', $roster['Anne']['seen_ago']);

        $this->assertSame(2, Presence::onlineCount());
    }

    public function test_les_comptes_desactives_et_les_absents_de_longue_date_sont_exclus(): void
    {
        User::factory()->create(['name' => 'Désactivé', 'is_active' => false, 'last_seen_at' => now()]);
        User::factory()->create(['name' => 'Disparu', 'is_active' => true, 'last_seen_at' => now()->subDays(3)]);
        User::factory()->create(['name' => 'Jamais venu', 'is_active' => true, 'last_seen_at' => null]);
        $present = User::factory()->create(['name' => 'Présent', 'is_active' => true, 'last_seen_at' => now()]);

        $noms = Presence::roster($present->id)->pluck('name');

        $this->assertEquals(['Présent'], $noms->all());
        $this->assertSame(1, Presence::onlineCount());
    }

    public function test_l_endpoint_repond_en_json_a_un_utilisateur_connecte(): void
    {
        $user = User::factory()->create(['is_active' => true, 'last_seen_at' => now()]);

        $response = $this->actingAs($user)->getJson('/presence');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'online',
                'users' => [['id', 'name', 'initials', 'avatar', 'is_online', 'is_self', 'seen_ago']],
            ]);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_l_endpoint_est_ferme_aux_visiteurs_anonymes(): void
    {
        $this->get('/presence')->assertRedirect('/login');
    }

    public function test_le_panneau_est_rendu_dans_la_sidebar(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/')
            ->assertSee('data-sidebar-section="presence"', false)
            ->assertSee('data-presence-list', false);
    }
}
