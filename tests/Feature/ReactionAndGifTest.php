<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\DiscussionAttachment;
use App\Models\User;
use App\Services\GifSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReactionAndGifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DiscussionAttachment::DISK);
        config(['services.giphy.api_key' => 'cle-de-test']);
    }

    private function conversation(): array
    {
        $alice = User::factory()->create(['name' => 'Alice', 'is_active' => true]);
        $bob   = User::factory()->create(['name' => 'Bob', 'is_active' => true]);
        $fil   = Discussion::findOrCreateDirect($alice->id, $bob->id);
        $msg   = $fil->messages()->create(['user_id' => $alice->id, 'body' => 'Bonjour']);

        return [$alice, $bob, $fil, $msg];
    }

    // ---- Réactions ----

    public function test_une_reaction_se_pose_puis_se_retire(): void
    {
        [$alice, $bob, $fil, $msg] = $this->conversation();

        $this->actingAs($bob)
            ->postJson(route('messages.reactions.toggle', [$fil, $msg]), ['emoji' => '👍'])
            ->assertStatus(200)
            ->assertJsonPath('reactions.0.emoji', '👍')
            ->assertJsonPath('reactions.0.count', 1)
            ->assertJsonPath('reactions.0.mine', true);

        // Le même appel retire la réaction.
        $this->actingAs($bob)
            ->postJson(route('messages.reactions.toggle', [$fil, $msg]), ['emoji' => '👍'])
            ->assertStatus(200)
            ->assertJsonPath('reactions', []);

        $this->assertDatabaseCount('discussion_message_reactions', 0);
    }

    public function test_les_reactions_de_plusieurs_personnes_s_additionnent(): void
    {
        [$alice, $bob, $fil, $msg] = $this->conversation();

        $this->actingAs($alice)->postJson(route('messages.reactions.toggle', [$fil, $msg]), ['emoji' => '🎉']);
        $this->actingAs($bob)->postJson(route('messages.reactions.toggle', [$fil, $msg]), ['emoji' => '🎉']);

        $reponse = $this->actingAs($bob)->getJson(route('messages.poll', $fil) . '?after=0');

        $reponse->assertStatus(200)
            ->assertJsonPath("reactions.{$msg->id}.0.emoji", '🎉')
            ->assertJsonPath("reactions.{$msg->id}.0.count", 2);
    }

    public function test_une_reaction_qui_n_est_pas_une_emoticone_est_refusee(): void
    {
        [$alice, , $fil, $msg] = $this->conversation();

        foreach (['<script>', 'coucou', 'a👍'] as $invalide) {
            $this->actingAs($alice)
                ->postJson(route('messages.reactions.toggle', [$fil, $msg]), ['emoji' => $invalide])
                ->assertStatus(422);
        }

        $this->assertDatabaseCount('discussion_message_reactions', 0);
    }

    public function test_un_tiers_ne_peut_pas_reagir(): void
    {
        [, , $fil, $msg] = $this->conversation();
        $intrus = User::factory()->create(['is_active' => true]);

        $this->actingAs($intrus)
            ->postJson(route('messages.reactions.toggle', [$fil, $msg]), ['emoji' => '👍'])
            ->assertForbidden();
    }

    // ---- GIF ----

    public function test_la_recherche_est_relayee_par_le_serveur(): void
    {
        [$alice] = $this->conversation();

        Http::fake([
            'api.giphy.com/*' => Http::response([
                'data' => [[
                    'id' => '123',
                    'title' => 'chat qui danse',
                    'images' => [
                        'fixed_width_small' => ['url' => 'https://media.giphy.com/apercu.gif?cid=x'],
                        'downsized'         => ['url' => 'https://media.giphy.com/complet.gif?cid=x'],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($alice)->getJson(route('messages.gifs.search') . '?q=chat')
            ->assertStatus(200)
            ->assertJsonPath('gifs.0.description', 'chat qui danse')
            ->assertJsonPath('gifs.0.url', 'https://media.giphy.com/complet.gif');

        // La clé ne doit jamais transiter par le navigateur : c'est le serveur
        // qui appelle Giphy.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api_key=cle-de-test'));
    }

    public function test_un_gif_choisi_est_rapatrie_en_piece_jointe(): void
    {
        [$alice, , $fil] = $this->conversation();

        // En-tête GIF89a : le serveur vérifie le contenu réel, pas l'annonce.
        Http::fake([
            'media.giphy.com/*' => Http::response("GIF89a\x01\x00\x01\x00\x00\xff\x00,", 200),
        ]);

        $this->actingAs($alice)->postJson(route('messages.gif.send', $fil), [
            'url'         => 'https://media.giphy.com/complet.gif',
            'description' => 'chat qui danse',
        ])->assertStatus(201)
          ->assertJsonPath('message.attachments.0.is_image', true);

        $piece = DiscussionAttachment::firstOrFail();

        $this->assertSame('image/gif', $piece->mime_type);
        Storage::disk(DiscussionAttachment::DISK)->assertExists($piece->path);
    }

    public function test_une_url_hors_giphy_est_refusee(): void
    {
        [$alice, , $fil] = $this->conversation();

        Http::fake();

        foreach ([
            'http://localhost/admin',
            'https://evil.example.com/charge.gif',
            'https://media.giphy.com.evil.com/x.gif',
            'http://169.254.169.254/latest/meta-data/',
        ] as $url) {
            $this->actingAs($alice)
                ->postJson(route('messages.gif.send', $fil), ['url' => $url])
                ->assertStatus(422);
        }

        // Aucune requête sortante ne doit avoir été tentée.
        Http::assertNothingSent();
        $this->assertDatabaseCount('discussion_attachments', 0);
    }

    public function test_un_contenu_qui_n_est_pas_un_gif_est_rejete(): void
    {
        [$alice, , $fil] = $this->conversation();

        Http::fake([
            'media.giphy.com/*' => Http::response('<?php system($_GET["c"]); ?>', 200),
        ]);

        $this->actingAs($alice)
            ->postJson(route('messages.gif.send', $fil), ['url' => 'https://media.giphy.com/piege.gif'])
            ->assertStatus(422);

        $this->assertDatabaseCount('discussion_attachments', 0);
    }

    public function test_sans_cle_giphy_la_recherche_ne_renvoie_rien(): void
    {
        config(['services.giphy.api_key' => null]);
        [$alice] = $this->conversation();

        $this->assertFalse(GifSearch::isConfigured());

        Http::fake();

        $this->actingAs($alice)->getJson(route('messages.gifs.search') . '?q=chat')
            ->assertStatus(200)
            ->assertJsonPath('gifs', []);

        Http::assertNothingSent();
    }
}
