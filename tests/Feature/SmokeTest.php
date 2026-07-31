<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Vérifie que les pages touchées par le durcissement rendent toujours,
 * nonce compris. Un <script> sans nonce serait bloqué par la CSP.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public static function pagesAuthentifiees(): array
    {
        return [
            'tableau de bord'   => ['/'],
            'chat'              => ['/chat'],
            'profil'            => ['/profile'],
            'préférences'       => ['/preferences'],
            'thème'             => ['/preferences/theme'],
            'messagerie'        => ['/messages'],
            'outil QR code'     => ['/tools/qr-code'],
            'convertisseur'     => ['/tools/image-converter'],
            'suppression fond'  => ['/tools/background-remover'],
        ];
    }

    #[DataProvider("pagesAuthentifiees")]
    public function test_les_pages_rendent_et_tous_leurs_scripts_portent_un_nonce(string $url): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get($url);

        $response->assertStatus(200);

        preg_match_all('/<script\b(?![^>]*\bnonce=)[^>]*>/i', $response->getContent(), $sansNonce);
        $this->assertSame([], $sansNonce[0], "Script sans nonce sur {$url}");
    }

    public static function pagesPubliques(): array
    {
        return [
            'connexion'         => ['/login'],
            'mot de passe oublié' => ['/forgot-password'],
        ];
    }

    #[DataProvider("pagesPubliques")]
    public function test_les_pages_publiques_rendent(string $url): void
    {
        $response = $this->get($url);

        $response->assertStatus(200);

        preg_match_all('/<script\b(?![^>]*\bnonce=)[^>]*>/i', $response->getContent(), $sansNonce);
        $this->assertSame([], $sansNonce[0], "Script sans nonce sur {$url}");
    }

    public function test_les_pages_admin_rendent_pour_un_administrateur(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        foreach (['/admin/tools', '/admin/families', '/admin/users', '/admin/assignments', '/admin/logs'] as $url) {
            $response = $this->actingAs($admin)->get($url);

            $response->assertStatus(200);

            preg_match_all('/<script\b(?![^>]*\bnonce=)[^>]*>/i', $response->getContent(), $sansNonce);
            $this->assertSame([], $sansNonce[0], "Script sans nonce sur {$url}");
        }
    }
}
