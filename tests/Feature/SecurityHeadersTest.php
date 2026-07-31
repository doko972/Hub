<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_en_tetes_de_securite_sont_presents(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_absent_en_http_et_present_en_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');
    }

    public function test_la_csp_interdit_les_scripts_inline_non_nonces(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'Aucun en-tête Content-Security-Policy.');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode('style-src', $csp)[0]);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_le_nonce_de_l_en_tete_correspond_a_celui_des_balises_script(): void
    {
        $response = $this->get('/login');
        $html     = $response->getContent();

        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $header);
        $this->assertNotEmpty($header[1] ?? null, 'Pas de nonce dans la CSP.');

        // Toutes les balises <script> de la page doivent porter ce nonce,
        // sinon le navigateur les bloquera.
        preg_match_all('/<script\b(?![^>]*\bnonce=)[^>]*>/i', $html, $sansNonce);

        $this->assertSame(
            [],
            $sansNonce[0],
            "Balise(s) <script> sans nonce : elles seraient bloquées par la CSP."
        );

        $this->assertStringContainsString('nonce="' . $header[1] . '"', $html);
    }

    public function test_la_csp_autorise_le_serveur_de_developpement_vite(): void
    {
        $hot = public_path('hot');

        // Ne pas perturber un `npm run dev` réellement en cours.
        if (file_exists($hot)) {
            $this->markTestSkipped('public/hot existe déjà (serveur Vite en cours).');
        }

        file_put_contents($hot, 'http://[::1]:5173');

        try {
            $csp = $this->get('/login')->headers->get('Content-Security-Policy');

            $this->assertStringContainsString('http://[::1]:5173', $csp);
            $this->assertStringContainsString('ws://[::1]:5173', $csp);
            // Le websocket HMR n'a rien à faire ailleurs que dans connect-src.
            $this->assertStringNotContainsString('ws://', explode('connect-src', $csp)[0]);
        } finally {
            @unlink($hot);
        }
    }

    public function test_la_csp_de_production_ne_contient_aucune_origine_de_developpement(): void
    {
        if (file_exists(public_path('hot'))) {
            $this->markTestSkipped('public/hot existe déjà (serveur Vite en cours).');
        }

        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString(':5173', $csp);
        $this->assertStringNotContainsString('ws://', $csp);
    }

    public function test_le_chat_ne_charge_plus_aucun_script_tiers(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $html = $this->actingAs($user)->get('/chat')->getContent();

        foreach (['cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com'] as $cdn) {
            $this->assertStringNotContainsString($cdn, $html, "Le chat charge encore {$cdn}.");
        }

        preg_match_all('/<script\b(?![^>]*\bnonce=)[^>]*>/i', $html, $sansNonce);
        $this->assertSame([], $sansNonce[0]);
    }
}
