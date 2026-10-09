<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garde les invariants du catalogue de thèmes.
 *
 * config/themes.php annonce que « ajouter un thème = 1 bloc ici + 1 bloc
 * [data-theme="clé"] dans _themes.scss ». Rien ne le vérifiait : une clé
 * ajoutée sans sa palette donnait un thème sélectionnable qui ne changeait
 * rien à l'écran, sans la moindre erreur.
 */
class ThemeCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function palettes(): string
    {
        return file_get_contents(resource_path('sass/components/_themes.scss'));
    }

    /**
     * 'system' n'a pas de palette (il est résolu en clair ou sombre), et
     * 'light' est le défaut porté par :root dans _variables.scss.
     */
    private function themesAvecPalette(): array
    {
        return array_diff(array_keys(config('themes.available')), ['system', 'light']);
    }

    public function test_chaque_theme_du_catalogue_a_sa_palette_scss(): void
    {
        $scss = $this->palettes();

        foreach ($this->themesAvecPalette() as $cle) {
            $this->assertStringContainsString(
                "[data-theme=\"{$cle}\"]",
                $scss,
                "Le thème « {$cle} » est proposé mais n'a aucune palette dans _themes.scss."
            );
        }
    }

    public function test_les_themes_sombres_sont_declares_des_deux_cotes(): void
    {
        preg_match('/\$dark-themes:([^;]+);/', $this->palettes(), $m);
        $this->assertNotEmpty($m, 'La liste $dark-themes est introuvable dans _themes.scss.');

        foreach ($this->themesAvecPalette() as $cle) {
            $sombreEnScss = str_contains($m[1], "'{$cle}'");
            $sombreEnPhp  = (bool) config("themes.available.{$cle}.dark");

            $this->assertSame(
                $sombreEnPhp,
                $sombreEnScss,
                "Le thème « {$cle} » est annoncé " . ($sombreEnPhp ? 'sombre' : 'clair')
                . " dans config/themes.php mais l'inverse dans \$dark-themes."
            );
        }
    }

    public function test_le_theme_centrex_est_selectionnable_et_enregistre(): void
    {
        $utilisateur = User::factory()->create(['is_active' => true]);

        $this->actingAs($utilisateur)
            ->postJson('/preferences/theme', ['theme' => 'centrex'])
            ->assertSuccessful();

        $this->assertSame('centrex', $utilisateur->fresh()->theme);
    }

    public function test_le_module_centrex_hr_n_embarque_plus_de_style_en_ligne(): void
    {
        // Le style vivait dans une balise <style> de la vue, avec sa propre
        // palette : la page ignorait le thème choisi.
        foreach (glob(resource_path('views/tools/centrex-hr/*.blade.php')) as $vue) {
            $this->assertStringNotContainsString(
                '<style',
                file_get_contents($vue),
                basename($vue) . ' embarque encore du style, qui échappera au thème.'
            );
        }
    }
}
