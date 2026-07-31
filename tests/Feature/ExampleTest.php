<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le tableau de bord est protégé : un visiteur anonyme est redirigé.
     */
    public function test_the_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    /**
     * …et reste accessible à un utilisateur actif.
     */
    public function test_the_dashboard_is_reachable_once_authenticated(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/')->assertStatus(200);
    }
}
