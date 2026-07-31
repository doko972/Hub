<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Couvre les correctifs de sécurité : bruteforce, comptes désactivés,
 * rattachement Google, inscription fermée et chiffrement des jetons Google.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('bob|127.0.0.1');
    }

    public function test_le_login_web_est_bloque_apres_cinq_echecs(): void
    {
        User::factory()->create(['name' => 'bob', 'is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['name' => 'bob', 'password' => 'mauvais']);
        }

        $response = $this->post('/login', ['name' => 'bob', 'password' => 'password']);

        // Même avec le bon mot de passe, la 6e tentative est refusée.
        $response->assertSessionHasErrors('name');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Trop de tentatives',
            session('errors')->first('name')
        );
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter_et_perd_ses_jetons(): void
    {
        $user = User::factory()->create(['name' => 'bob', 'is_active' => false]);
        $user->createToken('cortex-web-test');

        $this->assertSame(1, $user->tokens()->count());

        $response = $this->post('/login', ['name' => 'bob', 'password' => 'password']);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_le_login_api_refuse_un_compte_desactive(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_l_inscription_publique_par_api_n_existe_plus(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'Intrus',
            'email'                 => 'intrus@example.com',
            'password'              => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intrus@example.com']);
    }

    public function test_les_routes_google_exigent_une_session_authentifiee(): void
    {
        $this->get('/auth/google')->assertRedirect('/login');
        $this->get('/auth/google/callback')->assertRedirect('/login');
    }

    public function test_les_jetons_google_sont_chiffres_en_base(): void
    {
        $user = User::factory()->create();

        $user->update([
            'google_access_token'  => 'ya29.jeton-acces',
            'google_refresh_token' => '1//jeton-refresh',
        ]);

        $stored = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotSame('ya29.jeton-acces', $stored->google_access_token);
        $this->assertNotSame('1//jeton-refresh', $stored->google_refresh_token);

        // …mais restent lisibles via le modèle.
        $this->assertSame('ya29.jeton-acces', $user->fresh()->google_access_token);
        $this->assertSame('1//jeton-refresh', $user->fresh()->google_refresh_token);
    }

    public function test_les_routes_api_sont_soumises_a_une_limitation_de_debit(): void
    {
        $middleware = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/login')
            ->gatherMiddleware();

        $this->assertContains('throttle:auth', $middleware);
    }
}
