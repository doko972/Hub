<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionAttachment;
use App\Models\DiscussionMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageEditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DiscussionAttachment::DISK);
    }

    private function conversation(): array
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
        $fil   = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $msg   = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Version initiale']);

        return [$alice, $bob, $fil, $msg];
    }

    // ---- Modification ----

    public function test_l_auteur_modifie_son_message(): void
    {
        [$alice, , $fil, $msg] = $this->conversation();

        $this->actingAs($alice)
            ->patchJson(route('messages.messages.update', [$fil, $msg]), ['body' => 'Version corrigée'])
            ->assertStatus(200)
            ->assertJsonPath('message.body', 'Version corrigée')
            ->assertJsonPath('message.edited', true);

        $this->assertSame('Version corrigée', $msg->fresh()->body);
        $this->assertNotNull($msg->fresh()->edited_at);
    }

    public function test_un_autre_participant_ne_peut_pas_modifier(): void
    {
        [, $bob, $fil, $msg] = $this->conversation();

        $this->actingAs($bob)
            ->patchJson(route('messages.messages.update', [$fil, $msg]), ['body' => 'Détournement'])
            ->assertForbidden();

        $this->assertSame('Version initiale', $msg->fresh()->body);
    }

    public function test_un_message_sans_piece_jointe_ne_peut_pas_etre_vide(): void
    {
        [$alice, , $fil, $msg] = $this->conversation();

        $this->actingAs($alice)
            ->patchJson(route('messages.messages.update', [$fil, $msg]), ['body' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }

    public function test_un_message_avec_piece_jointe_peut_perdre_son_texte(): void
    {
        [$alice, , $fil] = $this->conversation();

        $this->actingAs($alice)->post(route('messages.send', $fil), [
            'body'        => 'Le rapport est joint',
            'attachments' => [UploadedFile::fake()->create('rapport.pdf', 10, 'application/pdf')],
        ]);

        $msg = DiscussionMessage::latest('id')->firstOrFail();

        $this->actingAs($alice)
            ->patchJson(route('messages.messages.update', [$fil, $msg]), ['body' => ''])
            ->assertStatus(200);

        $this->assertSame('', $msg->fresh()->body);
    }

    public function test_un_message_d_un_autre_fil_est_refuse(): void
    {
        [$alice, , $fil, $msg] = $this->conversation();
        $carol = User::factory()->create(['is_active' => true]);
        $autre = Discussion::findOrCreateDirect($alice->id, $carol->id);

        // Identifiants mélangés : le message n'appartient pas à ce fil.
        $this->actingAs($alice)
            ->patchJson(route('messages.messages.update', [$autre, $msg]), ['body' => 'Ailleurs'])
            ->assertNotFound();
    }

    // ---- Suppression ----

    public function test_l_auteur_supprime_son_message(): void
    {
        [$alice, , $fil, $msg] = $this->conversation();

        $this->actingAs($alice)
            ->deleteJson(route('messages.messages.destroy', [$fil, $msg]))
            ->assertStatus(200);

        // Suppression douce : la ligne subsiste pour informer les autres.
        $this->assertSoftDeleted('discussion_messages', ['id' => $msg->id]);

        // Mais elle disparaît du fil.
        $this->actingAs($alice)->get(route('messages.show', $fil))
            ->assertStatus(200)
            ->assertDontSee('Version initiale');
    }

    public function test_un_autre_participant_ne_peut_pas_supprimer(): void
    {
        [, $bob, $fil, $msg] = $this->conversation();

        $this->actingAs($bob)
            ->deleteJson(route('messages.messages.destroy', [$fil, $msg]))
            ->assertForbidden();

        $this->assertDatabaseHas('discussion_messages', ['id' => $msg->id, 'deleted_at' => null]);
    }

    public function test_supprimer_un_message_efface_ses_pieces_jointes(): void
    {
        [$alice, , $fil] = $this->conversation();

        $this->actingAs($alice)->post(route('messages.send', $fil), [
            'attachments' => [UploadedFile::fake()->create('confidentiel.pdf', 10, 'application/pdf')],
        ]);

        $msg    = DiscussionMessage::latest('id')->firstOrFail();
        $piece  = DiscussionAttachment::firstOrFail();
        $chemin = $piece->path;

        $this->actingAs($alice)->deleteJson(route('messages.messages.destroy', [$fil, $msg]))->assertStatus(200);

        // Le fichier doit réellement quitter le disque, pas seulement la vue.
        Storage::disk(DiscussionAttachment::DISK)->assertMissing($chemin);
        $this->assertDatabaseCount('discussion_attachments', 0);
    }

    // ---- Propagation aux autres participants ----

    public function test_le_sondage_annonce_modifications_et_suppressions(): void
    {
        [$alice, $bob, $fil, $msg] = $this->conversation();
        $second = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'À supprimer']);

        $this->actingAs($alice)->patchJson(route('messages.messages.update', [$fil, $msg]), ['body' => 'Corrigé']);
        $this->actingAs($alice)->deleteJson(route('messages.messages.destroy', [$fil, $second]));

        // Bob, qui a déjà les deux bulles affichées, doit apprendre les deux
        // évènements : ni l'un ni l'autre n'apparaît dans les nouveaux messages.
        $reponse = $this->actingAs($bob)->getJson(route('messages.poll', $fil) . '?after=' . $second->id);

        $reponse->assertStatus(200)
            ->assertJsonPath('messages', [])
            ->assertJsonPath("edited.{$msg->id}.body", 'Corrigé')
            ->assertJsonPath("edited.{$msg->id}.edited", true);

        $this->assertContains($second->id, $reponse->json('deleted'));
    }

    public function test_un_message_supprime_ne_compte_plus_comme_non_lu(): void
    {
        [$alice, $bob, $fil] = $this->conversation();
        $msg = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Oups']);

        $this->assertSame(2, \App\Services\Unread::totalFor($bob->id));

        $this->actingAs($alice)->deleteJson(route('messages.messages.destroy', [$fil, $msg]));

        $this->assertSame(1, \App\Services\Unread::totalFor($bob->id));
    }
}
