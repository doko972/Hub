<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\User;
use App\Services\Unread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'Testeur'): User
    {
        return User::factory()->create(['name' => $name, 'is_active' => true]);
    }

    // ---- Conversations directes ----

    public function test_une_conversation_directe_n_est_jamais_dupliquee(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $premiere = Discussion::findOrCreateDirect($alice->id, $bob->id);
        // Ouverture par l'autre bout : l'ordre des identifiants ne doit rien changer.
        $seconde  = Discussion::findOrCreateDirect($bob->id, $alice->id);

        $this->assertSame($premiere->id, $seconde->id);
        $this->assertDatabaseCount('discussions', 1);
        $this->assertCount(2, $premiere->participants);
    }

    public function test_le_titre_d_une_conversation_depend_de_qui_regarde(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id)->load('participants');

        $this->assertSame('Bob', $discussion->titleFor($alice->id));
        $this->assertSame('Alice', $discussion->titleFor($bob->id));
    }

    public function test_on_ne_peut_pas_ouvrir_une_conversation_avec_soi_meme(): void
    {
        $alice = $this->user('Alice');

        $this->actingAs($alice)
            ->post(route('messages.direct', $alice))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseCount('discussions', 0);
    }

    // ---- Isolation ----

    public function test_un_tiers_ne_peut_ni_lire_ni_ecrire_dans_un_fil(): void
    {
        $alice   = $this->user('Alice');
        $bob     = $this->user('Bob');
        $intrus  = $this->user('Intrus');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Secret professionnel']);

        $this->actingAs($intrus)->get(route('messages.show', $discussion))->assertForbidden();
        $this->actingAs($intrus)->getJson(route('messages.poll', $discussion))->assertForbidden();
        $this->actingAs($intrus)->postJson(route('messages.send', $discussion), ['body' => 'Coucou'])->assertForbidden();

        $this->assertDatabaseMissing('discussion_messages', ['body' => 'Coucou']);
    }

    public function test_la_liste_ne_montre_que_ses_propres_fils(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');
        $carol = $this->user('Carol');

        Discussion::findOrCreateDirect($alice->id, $bob->id);
        $sansAlice = Discussion::findOrCreateDirect($bob->id, $carol->id);
        $sansAlice->messages()->create(['user_id' => $bob->id, 'body' => 'Conversation privée de Bob et Carol']);

        $this->actingAs($alice)->get(route('messages.index'))
            ->assertStatus(200)
            ->assertDontSee('Conversation privée de Bob et Carol')
            ->assertSee('Bob');
    }

    // ---- Envoi et réception ----

    public function test_l_envoi_puis_le_sondage_transmettent_le_message(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);

        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), ['body' => 'Bonjour Bob'])
            ->assertStatus(201)
            ->assertJsonPath('message.body', 'Bonjour Bob')
            ->assertJsonPath('message.is_mine', true);

        // Bob voit le message et n'en est pas l'auteur.
        $this->actingAs($bob)
            ->getJson(route('messages.poll', $discussion) . '?after=0')
            ->assertStatus(200)
            ->assertJsonPath('messages.0.body', 'Bonjour Bob')
            ->assertJsonPath('messages.0.is_mine', false)
            ->assertJsonPath('messages.0.author', 'Alice');
    }

    public function test_le_sondage_ne_renvoie_que_les_messages_posterieurs(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $premier = $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Premier']);
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Second']);

        $response = $this->actingAs($bob)
            ->getJson(route('messages.poll', $discussion) . '?after=' . $premier->id);

        $response->assertStatus(200)->assertJsonCount(1, 'messages');
        $this->assertSame('Second', $response->json('messages.0.body'));
    }

    public function test_un_message_vide_ou_trop_long_est_refuse(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');
        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);

        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), ['body' => '   '])
            ->assertStatus(422);

        $this->actingAs($alice)
            ->postJson(route('messages.send', $discussion), ['body' => str_repeat('a', 5001)])
            ->assertStatus(422);
    }

    // ---- Non-lus ----

    public function test_les_non_lus_excluent_ses_propres_messages_et_se_remettent_a_zero(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Un']);
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Deux']);

        // Alice ne se compte pas elle-même ; Bob a deux messages en attente.
        $this->assertSame(0, Unread::totalFor($alice->id));
        $this->assertSame(2, Unread::totalFor($bob->id));

        // L'ouverture du fil vaut lecture.
        $this->actingAs($bob)->get(route('messages.show', $discussion))->assertStatus(200);

        $this->assertSame(0, Unread::totalFor($bob->id));
    }

    public function test_l_endpoint_de_non_lus_renvoie_le_detail_par_fil(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Coucou']);

        $this->actingAs($bob)->getJson(route('messages.unread'))
            ->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath("per_discussion.{$discussion->id}", 1);
    }

    // ---- Groupes ----

    public function test_un_groupe_se_cree_avec_ses_membres_et_son_auteur(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');
        $carol = $this->user('Carol');

        $this->actingAs($alice)->post(route('messages.groups.store'), [
            'name'    => 'Équipe support',
            'members' => [$bob->id, $carol->id],
        ])->assertRedirect();

        $groupe = Discussion::where('is_group', true)->firstOrFail();

        $this->assertSame('Équipe support', $groupe->name);
        $this->assertNull($groupe->direct_key);
        $this->assertCount(3, $groupe->participants);
        $this->assertSame('Équipe support', $groupe->titleFor($bob->id));
    }

    public function test_seul_le_createur_ajoute_des_participants(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');
        $carol = $this->user('Carol');

        $this->actingAs($alice)->post(route('messages.groups.store'), [
            'name'    => 'Projet',
            'members' => [$bob->id],
        ]);

        $groupe = Discussion::where('is_group', true)->firstOrFail();

        // Bob est membre, mais n'a pas créé le groupe.
        $this->actingAs($bob)
            ->post(route('messages.participants.add', $groupe), ['members' => [$carol->id]])
            ->assertForbidden();

        $this->actingAs($alice)
            ->post(route('messages.participants.add', $groupe), ['members' => [$carol->id]])
            ->assertRedirect();

        $this->assertCount(3, $groupe->fresh()->participants);
    }

    public function test_on_quitte_un_groupe_mais_pas_une_conversation_a_deux(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $directe = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $this->actingAs($alice)->post(route('messages.leave', $directe))->assertForbidden();

        $this->actingAs($alice)->post(route('messages.groups.store'), [
            'name'    => 'Groupe',
            'members' => [$bob->id],
        ]);
        $groupe = Discussion::where('is_group', true)->firstOrFail();

        $this->actingAs($bob)->post(route('messages.leave', $groupe))->assertRedirect();

        $this->assertCount(1, $groupe->fresh()->participants);
    }

    public function test_le_texte_d_un_message_n_est_jamais_interprete_comme_du_html(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');
        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);

        $charge = '<img src=x onerror=alert(1)>';
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => $charge]);

        $this->actingAs($bob)->get(route('messages.show', $discussion))
            ->assertStatus(200)
            ->assertDontSee($charge, false)
            ->assertSee(e($charge), false);
    }
}
