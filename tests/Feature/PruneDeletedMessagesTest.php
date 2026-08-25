<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionAttachment;
use App\Models\DiscussionMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneDeletedMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function messageSupprime(int $ilYaJours): DiscussionMessage
    {
        $alice = User::factory()->create(['is_active' => true]);
        $bob   = User::factory()->create(['is_active' => true]);
        $fil   = Discussion::findOrCreateDirect($alice->id, $bob->id);

        $message = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'À purger']);
        $message->delete();
        $message->forceFill(['deleted_at' => now()->subDays($ilYaJours)])->saveQuietly();

        return $message;
    }

    public function test_les_traces_anciennes_sont_effacees(): void
    {
        $ancien = $this->messageSupprime(45);

        $this->artisan('messages:prune')
            ->expectsOutputToContain('1 trace(s) effacée(s)')
            ->assertSuccessful();

        // Plus rien, même en incluant les suppressions douces.
        $this->assertNull(DiscussionMessage::withTrashed()->find($ancien->id));
    }

    public function test_les_traces_recentes_sont_conservees(): void
    {
        $recent = $this->messageSupprime(3);

        $this->artisan('messages:prune')->assertSuccessful();

        // Un client peut encore avoir la bulle affichée : la trace sert
        // justement à lui annoncer la disparition.
        $this->assertNotNull(DiscussionMessage::withTrashed()->find($recent->id));
    }

    public function test_le_delai_est_reglable(): void
    {
        $message = $this->messageSupprime(10);

        $this->artisan('messages:prune --days=30')->assertSuccessful();
        $this->assertNotNull(DiscussionMessage::withTrashed()->find($message->id));

        $this->artisan('messages:prune --days=7')->assertSuccessful();
        $this->assertNull(DiscussionMessage::withTrashed()->find($message->id));
    }

    public function test_le_mode_simulation_ne_supprime_rien(): void
    {
        $message = $this->messageSupprime(45);

        $this->artisan('messages:prune --dry-run')
            ->expectsOutputToContain('seraient effacées')
            ->assertSuccessful();

        $this->assertNotNull(DiscussionMessage::withTrashed()->find($message->id));
    }

    public function test_les_messages_vivants_ne_sont_jamais_touches(): void
    {
        $alice = User::factory()->create(['is_active' => true]);
        $bob   = User::factory()->create(['is_active' => true]);
        $fil   = Discussion::findOrCreateDirect($alice->id, $bob->id);

        // Ancien, mais bien vivant : l'âge ne doit jouer que sur deleted_at.
        $vivant = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Toujours là']);
        $vivant->forceFill(['created_at' => now()->subYear()])->saveQuietly();

        $this->artisan('messages:prune --days=1')->assertSuccessful();

        $this->assertNotNull(DiscussionMessage::find($vivant->id));
    }

    public function test_la_purge_passe_par_le_modele_pour_nettoyer_le_disque(): void
    {
        Storage::fake(DiscussionAttachment::DISK);

        $message = $this->messageSupprime(45);

        // Pièce jointe rattachée après coup, pour simuler une trace qui en
        // conserverait une : la purge doit emporter le fichier avec elle.
        Storage::disk(DiscussionAttachment::DISK)->put('discussions/1/vestige.pdf', 'contenu');
        $message->attachments()->create([
            'path'          => 'discussions/1/vestige.pdf',
            'original_name' => 'vestige.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 7,
        ]);

        $this->artisan('messages:prune')->assertSuccessful();

        Storage::disk(DiscussionAttachment::DISK)->assertMissing('discussions/1/vestige.pdf');
        $this->assertDatabaseCount('discussion_attachments', 0);
    }
}
