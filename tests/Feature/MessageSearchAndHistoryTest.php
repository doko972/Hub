<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageSearchAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(): array
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);

        return [$alice, $bob, Discussion::findOrCreateDirect($alice->id, $bob->id)];
    }

    // ---- Recherche ----

    public function test_la_recherche_trouve_un_message_et_pointe_au_bon_endroit(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Le devis Dupont est signé']);
        $cible = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Facture Martin envoyée hier']);

        $reponse = $this->actingAs($bob)->getJson(route('messages.search') . '?q=Martin');

        $reponse->assertStatus(200)->assertJsonCount(1, 'results');
        $this->assertSame('Alice', $reponse->json('results.0.author'));
        // Le lien ouvre le fil positionné sur le message trouvé.
        $this->assertStringContainsString('?at=' . $cible->id, $reponse->json('results.0.url'));
    }

    public function test_la_recherche_ne_sort_jamais_des_fils_de_l_utilisateur(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
        $carol = User::factory()->create(['name' => 'Carol', 'is_active' => true]);

        // Conversation entre Bob et Carol, à laquelle Alice n'appartient pas.
        $prive = Discussion::findOrCreateDirect($bob->id, $carol->id);
        $prive->messages()->create(['user_id' => $bob->id, 'body' => 'Négociation confidentielle Martin']);

        $this->actingAs($alice)->getJson(route('messages.search') . '?q=Martin')
            ->assertStatus(200)
            ->assertJsonCount(0, 'results');
    }

    public function test_les_messages_supprimes_sont_exclus_de_la_recherche(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $message = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Erreur Martin à oublier']);
        $message->delete();

        $this->actingAs($bob)->getJson(route('messages.search') . '?q=Martin')
            ->assertStatus(200)
            ->assertJsonCount(0, 'results');
    }

    public function test_les_jokers_sql_ne_sont_pas_interpretes(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Un message ordinaire']);
        $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Remise de 20% accordée']);

        // « % » seul ne doit pas tout ramener : il est recherché littéralement.
        $reponse = $this->actingAs($bob)->getJson(route('messages.search') . '?q=' . urlencode('20%'));

        $reponse->assertStatus(200)->assertJsonCount(1, 'results');
        $this->assertStringContainsString('20%', $reponse->json('results.0.excerpt'));
    }

    public function test_une_recherche_trop_courte_est_refusee(): void
    {
        [$alice] = $this->conversation();

        $this->actingAs($alice)->getJson(route('messages.search') . '?q=a')->assertStatus(422);
    }

    // ---- Historique ----

    public function test_le_fil_ne_charge_qu_une_fenetre_et_signale_la_suite(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        foreach (range(1, 120) as $i) {
            $fil->messages()->create(['user_id' => $alice->id, 'body' => "Message {$i}"]);
        }

        $reponse = $this->actingAs($bob)->get(route('messages.show', $fil));

        $reponse->assertStatus(200)
            ->assertSee('Message 120')
            // Au-delà de la fenêtre de 100, les plus anciens ne sont pas rendus.
            ->assertDontSee('Message 1<')
            ->assertSee('data-load-older', false);
    }

    public function test_on_remonte_dans_l_historique_par_paliers(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        foreach (range(1, 120) as $i) {
            $fil->messages()->create(['user_id' => $alice->id, 'body' => "Message {$i}"]);
        }

        $plusAncienAffiche = DiscussionMessage::orderByDesc('id')->skip(99)->first();

        $reponse = $this->actingAs($bob)
            ->getJson(route('messages.history', $fil) . '?before=' . $plusAncienAffiche->id);

        $reponse->assertStatus(200);
        // 20 messages restaient avant la fenêtre initiale.
        $this->assertCount(20, $reponse->json('messages'));
        $this->assertFalse($reponse->json('has_older'));
        $this->assertSame('Message 1', $reponse->json('messages.0.body'));
    }

    public function test_un_tiers_ne_peut_pas_remonter_l_historique(): void
    {
        [, , $fil] = $this->conversation();
        $intrus = User::factory()->create(['is_active' => true]);

        $this->actingAs($intrus)
            ->getJson(route('messages.history', $fil) . '?before=999')
            ->assertForbidden();
    }

    public function test_l_ouverture_sur_un_message_ancien_le_place_dans_la_fenetre(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $ancien = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Tout premier message']);

        foreach (range(1, 150) as $i) {
            $fil->messages()->create(['user_id' => $alice->id, 'body' => "Message {$i}"]);
        }

        // Sans cible, ce message est hors fenêtre…
        $this->actingAs($bob)->get(route('messages.show', $fil))
            ->assertDontSee('Tout premier message');

        // …mais un résultat de recherche l'ouvre au bon endroit.
        $this->actingAs($bob)->get(route('messages.show', $fil) . '?at=' . $ancien->id)
            ->assertStatus(200)
            ->assertSee('Tout premier message')
            ->assertSee('data-focused-id="' . $ancien->id . '"', false);
    }
}
