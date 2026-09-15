<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionAttachment;
use App\Models\User;
use App\Services\Unread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Suppression d'une conversation : propre à celui qui la supprime.
 */
class ClearDiscussionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name): User
    {
        return User::factory()->create(['name' => $name, 'is_active' => true]);
    }

    /**
     * @return array{0: User, 1: User, 2: Discussion}
     */
    private function conversation(): array
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $discussion = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $discussion->messages()->create(['user_id' => $alice->id, 'body' => 'Ancien message confidentiel']);
        $discussion->messages()->create(['user_id' => $bob->id,   'body' => 'Ancienne réponse']);

        return [$alice, $bob, $discussion];
    }

    public function test_la_conversation_disparait_de_ma_liste_mais_pas_de_celle_de_l_autre(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        $this->actingAs($alice)
            ->delete(route('messages.clear', $discussion))
            ->assertRedirect(route('messages.index'));

        $this->actingAs($alice)->get(route('messages.index'))
            ->assertViewHas('discussions', fn ($d) => !$d->contains('id', $discussion->id));

        $this->actingAs($bob)->get(route('messages.index'))
            ->assertViewHas('discussions', fn ($d) => $d->contains('id', $discussion->id));

        // Rien n'est effacé en base : l'autre participant garde tout.
        $this->assertDatabaseCount('discussion_messages', 2);
        $this->assertTrue($discussion->fresh()->participants->contains('id', $alice->id));
    }

    public function test_l_historique_supprime_n_est_plus_servi_nulle_part(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        $this->actingAs($alice)->delete(route('messages.clear', $discussion));

        $this->actingAs($alice)->get(route('messages.show', $discussion))
            ->assertViewHas('messages', fn ($m) => $m->isEmpty())
            ->assertDontSee('Ancien message confidentiel');

        $this->actingAs($alice)->getJson(route('messages.poll', $discussion) . '?after=0')
            ->assertJsonCount(0, 'messages');

        $this->actingAs($alice)->getJson(route('messages.history', $discussion) . '?before=999999')
            ->assertJsonCount(0, 'messages');

        $this->actingAs($alice)->getJson(route('messages.search') . '?q=confidentiel')
            ->assertJsonCount(0, 'results');

        // L'autre, lui, retrouve tout.
        $this->actingAs($bob)->getJson(route('messages.poll', $discussion) . '?after=0')
            ->assertJsonCount(2, 'messages');

        $this->actingAs($bob)->getJson(route('messages.search') . '?q=confidentiel')
            ->assertJsonCount(1, 'results');
    }

    public function test_un_nouveau_message_fait_reapparaitre_la_conversation_sans_l_historique(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        $this->actingAs($alice)->delete(route('messages.clear', $discussion));

        $this->actingAs($bob)->postJson(route('messages.send', $discussion), ['body' => 'Tu es là ?'])
            ->assertCreated();

        $this->actingAs($alice)->get(route('messages.index'))
            ->assertViewHas('discussions', fn ($d) => $d->contains('id', $discussion->id));

        $this->actingAs($alice)->get(route('messages.show', $discussion))
            ->assertViewHas('messages', fn ($m) => $m->pluck('body')->all() === ['Tu es là ?']);
    }

    public function test_les_non_lus_de_la_conversation_supprimee_ne_comptent_plus(): void
    {
        [$alice, $bob, $discussion] = $this->conversation();

        // Alice n'a jamais ouvert le fil : la réponse de Bob est non lue.
        $this->assertSame(1, Unread::totalFor($alice->id));

        $this->actingAs($alice)->delete(route('messages.clear', $discussion));

        $this->assertSame(0, Unread::totalFor($alice->id));
        $this->assertNull(Unread::latestFor($alice->id));

        // La frontière suffit à elle seule, indépendamment de la frontière de
        // lecture que la suppression remet aussi à jour.
        DB::table('discussion_user')->where('user_id', $alice->id)->update(['last_read_at' => null]);

        $this->assertSame(0, Unread::totalFor($alice->id));
    }

    public function test_une_piece_jointe_de_l_historique_supprime_n_est_plus_servie(): void
    {
        Storage::fake(DiscussionAttachment::DISK);

        [$alice, $bob, $discussion] = $this->conversation();

        $this->actingAs($bob)->post(route('messages.send', $discussion), [
            'attachments' => [UploadedFile::fake()->create('contrat.txt', 5, 'text/plain')],
        ]);

        $attachment = DiscussionAttachment::firstOrFail();

        $this->actingAs($alice)->get(route('messages.attachment', $attachment))->assertOk();

        $this->actingAs($alice)->delete(route('messages.clear', $discussion));

        $this->actingAs($alice)->get(route('messages.attachment', $attachment))->assertNotFound();
        $this->actingAs($bob)->get(route('messages.attachment', $attachment))->assertOk();
    }

    public function test_supprimer_un_groupe_ne_revient_pas_a_le_quitter(): void
    {
        $alice = $this->user('Alice');
        $bob   = $this->user('Bob');

        $this->actingAs($alice)->post(route('messages.groups.store'), [
            'name'    => 'Projet',
            'members' => [$bob->id],
        ]);

        $groupe = Discussion::where('is_group', true)->firstOrFail();
        $groupe->messages()->create(['user_id' => $bob->id, 'body' => 'Point du lundi']);

        $this->actingAs($alice)->delete(route('messages.clear', $groupe))
            ->assertRedirect(route('messages.index'));

        // Toujours membre : elle recevra les prochains messages.
        $this->assertTrue($groupe->fresh()->participants->contains('id', $alice->id));
    }

    public function test_un_tiers_ne_peut_pas_supprimer_la_conversation(): void
    {
        [, , $discussion] = $this->conversation();
        $intrus = $this->user('Intrus');

        $this->actingAs($intrus)->delete(route('messages.clear', $discussion))->assertForbidden();

        $this->assertSame(0, DB::table('discussion_user')->whereNotNull('cleared_through_id')->count());
    }

    public function test_le_bouton_est_present_dans_l_en_tete_du_fil(): void
    {
        [$alice, , $discussion] = $this->conversation();

        $this->actingAs($alice)->get(route('messages.show', $discussion))
            ->assertSee(route('messages.clear', $discussion), false)
            ->assertSee('Supprimer la conversation');
    }
}
