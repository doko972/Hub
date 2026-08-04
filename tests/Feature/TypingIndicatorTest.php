<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\User;
use App\Services\Typing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypingIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(): array
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);

        return [$alice, $bob, Discussion::findOrCreateDirect($alice->id, $bob->id)];
    }

    public function test_le_signal_est_visible_par_l_autre_participant(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $this->actingAs($alice)
            ->postJson(route('messages.typing', $fil), ['typing' => true])
            ->assertStatus(200);

        // Bob voit qu'Alice écrit…
        $this->actingAs($bob)->getJson(route('messages.poll', $fil) . '?after=0')
            ->assertStatus(200)
            ->assertJsonPath('typing', ['Alice']);

        // …et Alice ne se voit pas elle-même.
        $this->actingAs($alice)->getJson(route('messages.poll', $fil) . '?after=0')
            ->assertJsonPath('typing', []);
    }

    public function test_le_signal_s_arrete_a_la_demande(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messages.typing', $fil), ['typing' => true]);
        $this->actingAs($alice)->postJson(route('messages.typing', $fil), ['typing' => false]);

        $this->actingAs($bob)->getJson(route('messages.poll', $fil) . '?after=0')
            ->assertJsonPath('typing', []);
    }

    public function test_le_signal_expire_de_lui_meme(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messages.typing', $fil), ['typing' => true]);

        // Navigateur fermé brutalement : plus personne ne renouvelle le signal.
        $this->travel(config('messaging.typing_ttl_seconds') + 2)->seconds();

        $this->actingAs($bob)->getJson(route('messages.poll', $fil) . '?after=0')
            ->assertJsonPath('typing', []);
    }

    public function test_envoyer_un_message_retire_le_signal(): void
    {
        [$alice, $bob, $fil] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messages.typing', $fil), ['typing' => true]);
        $this->actingAs($alice)->postJson(route('messages.send', $fil), ['body' => 'Voilà !']);

        // Le message est arrivé : laisser « Alice écrit… » serait absurde.
        $this->actingAs($bob)->getJson(route('messages.poll', $fil) . '?after=0')
            ->assertJsonPath('typing', []);
    }

    public function test_plusieurs_personnes_peuvent_ecrire_en_meme_temps(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
        $carol = User::factory()->create(['name' => 'Carol', 'is_active' => true]);

        $groupe = Discussion::create(['name' => 'Équipe', 'is_group' => true, 'created_by' => $alice->id]);
        $groupe->participants()->attach([$alice->id, $bob->id, $carol->id]);

        $this->actingAs($alice)->postJson(route('messages.typing', $groupe), ['typing' => true]);
        $this->actingAs($bob)->postJson(route('messages.typing', $groupe), ['typing' => true]);

        $reponse = $this->actingAs($carol)->getJson(route('messages.poll', $groupe) . '?after=0');

        $this->assertEqualsCanonicalizing(['Alice', 'Bob'], $reponse->json('typing'));
    }

    public function test_un_tiers_ne_peut_pas_signaler_dans_un_fil(): void
    {
        [, , $fil] = $this->conversation();
        $intrus = User::factory()->create(['is_active' => true]);

        $this->actingAs($intrus)
            ->postJson(route('messages.typing', $fil), ['typing' => true])
            ->assertForbidden();
    }

    public function test_le_signal_ne_touche_pas_la_base(): void
    {
        [$alice, , $fil] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messages.typing', $fil), ['typing' => true]);

        // Rien à purger : l'information est purement volatile.
        $this->assertSame(['Alice'], Typing::othersFor($fil->id, 0));
        $this->assertDatabaseCount('discussion_messages', 0);
    }
}
