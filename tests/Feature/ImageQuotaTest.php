<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ImageQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(User $user): Conversation
    {
        return $user->conversations()->create(['title' => 'Test']);
    }

    public function test_les_deux_chemins_de_generation_sont_comptabilises(): void
    {
        $user         = User::factory()->create();
        $conversation = $this->conversation($user);

        // Chemin /imagine
        $conversation->messages()->create([
            'role'    => 'user',
            'content' => '/imagine un chat astronaute',
        ]);

        // Chemin outil generate_image (contournait le quota auparavant)
        $conversation->messages()->create([
            'role'       => 'assistant',
            'content'    => 'un chat astronaute',
            'has_image'  => true,
            'image_path' => "chat-images/{$user->id}/dalle-abc.png",
        ]);

        $this->assertSame(2, ImageQuota::usedToday($user->id));
        $this->assertSame(ImageQuota::DAILY_LIMIT - 2, ImageQuota::remaining($user->id));
        $this->assertFalse(ImageQuota::exceeded($user->id));
    }

    public function test_le_quota_est_atteint_a_la_limite(): void
    {
        $user         = User::factory()->create();
        $conversation = $this->conversation($user);

        for ($i = 0; $i < ImageQuota::DAILY_LIMIT; $i++) {
            $conversation->messages()->create([
                'role'    => 'user',
                'content' => "/imagine image {$i}",
            ]);
        }

        $this->assertTrue(ImageQuota::exceeded($user->id));
        $this->assertSame(0, ImageQuota::remaining($user->id));
    }

    public function test_le_quota_est_cloisonne_par_utilisateur(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();

        $this->conversation($alice)->messages()->create([
            'role'    => 'user',
            'content' => '/imagine quelque chose',
        ]);

        $this->assertSame(1, ImageQuota::usedToday($alice->id));
        $this->assertSame(0, ImageQuota::usedToday($bob->id));
    }

    public function test_les_images_d_hier_ne_comptent_plus(): void
    {
        $user         = User::factory()->create();
        $conversation = $this->conversation($user);

        $message = $conversation->messages()->create([
            'role'    => 'user',
            'content' => '/imagine hier',
        ]);
        $message->forceFill(['created_at' => now()->subDay()])->save();

        $this->assertSame(0, ImageQuota::usedToday($user->id));
    }
}
