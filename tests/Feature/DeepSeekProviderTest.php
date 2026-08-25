<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ChatController;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bascule d'OpenAI vers DeepSeek.
 *
 * DeepSeek expose la même API que celle d'OpenAI : le client est le même, seules
 * la clé et l'URL de base changent.
 */
class DeepSeekProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.deepseek.api_key' => 'cle-deepseek',
            'openai.api_key'            => null,
            'services.anthropic.api_key' => null,
            'ai.default_model'          => 'deepseek-chat',
        ]);
    }

    public function test_le_catalogue_contient_les_modeles_deepseek(): void
    {
        $this->assertSame('deepseek', ChatController::$models['deepseek-chat']['provider']);
        $this->assertSame('deepseek', ChatController::$models['deepseek-reasoner']['provider']);
    }

    public function test_seuls_les_modeles_configures_sont_proposes(): void
    {
        $disponibles = array_keys(ChatController::availableModels());

        $this->assertContains('deepseek-chat', $disponibles);
        // Sans clé OpenAI ni Anthropic, ces modèles ne doivent pas apparaître.
        $this->assertNotContains('gpt-4o', $disponibles);
        $this->assertNotContains('claude-sonnet-4-20250514', $disponibles);
    }

    public function test_une_cle_openai_fait_reapparaitre_les_modeles_gpt(): void
    {
        config(['openai.api_key' => 'cle-openai']);

        $this->assertContains('gpt-4o', array_keys(ChatController::availableModels()));
    }

    public function test_le_selecteur_de_la_page_de_chat_suit_le_catalogue(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/chat')
            ->assertStatus(200)
            ->assertSee('DeepSeek V3')
            ->assertDontSee('GPT-4o');
    }
}
