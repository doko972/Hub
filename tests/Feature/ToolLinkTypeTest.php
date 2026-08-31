<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolFamily;
use App\Models\User;
use App\Rules\ToolLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un outil peut viser une adresse web ou un chemin réseau.
 *
 * Le second n'est pas ouvrable par le navigateur : la vignette copie alors le
 * chemin au lieu de proposer un lien mort.
 */
class ToolLinkTypeTest extends TestCase
{
    use RefreshDatabase;

    private const BS = "\\";

    private ToolFamily $famille;

    protected function setUp(): void
    {
        parent::setUp();

        $this->famille = ToolFamily::create(['name' => 'Réseau', 'is_active' => true, 'sort_order' => 1]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'admin']);
    }

    private function donnees(array $extra = []): array
    {
        return array_merge([
            'title'          => 'Partage HR',
            'link_type'      => 'web',
            'url'            => 'https://intranet.exemple.fr',
            'color'          => 'violet',
            'tool_family_id' => $this->famille->id,
            'is_active'      => 1,
            'is_public'      => 1,
            'sort_order'     => 0,
        ], $extra);
    }

    // ---- Validation ----

    public function test_un_chemin_reseau_est_accepte_comme_destination(): void
    {
        $chemin = 'H:' . self::BS . 'HR TELECOMS';

        $this->actingAs($this->admin())
            ->post(route('admin.tools.store'), $this->donnees([
                'link_type' => 'path',
                'url'       => $chemin,
            ]))
            ->assertRedirect(route('admin.tools.index'));

        $outil = Tool::firstOrFail();

        $this->assertSame('path', $outil->link_type);
        $this->assertSame($chemin, $outil->url);
        $this->assertTrue($outil->isPath());
    }

    public function test_un_chemin_unc_est_accepte(): void
    {
        $chemin = self::BS . self::BS . 'nas' . self::BS . 'HR TELECOMS';

        $this->actingAs($this->admin())
            ->post(route('admin.tools.store'), $this->donnees(['link_type' => 'path', 'url' => $chemin]))
            ->assertRedirect();

        $this->assertSame($chemin, Tool::firstOrFail()->url);
    }

    public function test_un_chemin_ne_passe_pas_pour_une_adresse_web(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.tools.store'), $this->donnees([
                'link_type' => 'web',
                'url'       => 'H:' . self::BS . 'HR TELECOMS',
            ]))
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('tools', 0);
    }

    public function test_du_texte_libre_ne_passe_pas_pour_un_chemin(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.tools.store'), $this->donnees([
                'link_type' => 'path',
                'url'       => 'le dossier partagé',
            ]))
            ->assertSessionHasErrors('url');

        $this->assertDatabaseCount('tools', 0);
    }

    public function test_les_schemas_exotiques_sont_refuses_en_web(): void
    {
        // smb:// passe la règle « url » de Laravel mais aucun navigateur ne
        // sait l'ouvrir : la vignette serait morte.
        foreach (['smb://nas/partage', 'file:///C:/doc.pdf', 'javascript:alert(1)'] as $adresse) {
            $this->actingAs($this->admin())
                ->post(route('admin.tools.store'), $this->donnees(['url' => $adresse]))
                ->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('tools', 0);
    }

    // ---- Affichage ----

    public function test_la_vignette_d_un_chemin_copie_au_lieu_de_lier(): void
    {
        $chemin = 'H:' . self::BS . 'HR TELECOMS';

        Tool::create([
            'title'          => 'Partage HR',
            'url'            => $chemin,
            'link_type'      => 'path',
            'tool_family_id' => $this->famille->id,
            'is_active'      => true,
            'is_public'      => true,
            'sort_order'     => 1,
        ]);

        $reponse = $this->actingAs(User::factory()->create(['is_active' => true]))->get('/');

        $reponse->assertStatus(200)
            ->assertSee('data-copy-path', false)
            // Surtout pas de lien : le navigateur ne le suivrait pas.
            ->assertDontSee('href="H:', false);
    }

    public function test_la_vignette_d_une_adresse_web_reste_un_lien(): void
    {
        Tool::create([
            'title'          => 'Intranet',
            'url'            => 'https://intranet.exemple.fr',
            'link_type'      => 'web',
            'tool_family_id' => $this->famille->id,
            'is_active'      => true,
            'is_public'      => true,
            'sort_order'     => 1,
        ]);

        $this->actingAs(User::factory()->create(['is_active' => true]))->get('/')
            ->assertStatus(200)
            ->assertSee('href="https://intranet.exemple.fr"', false)
            ->assertDontSee('data-copy-path', false);
    }

    public function test_les_outils_existants_restent_des_adresses_web(): void
    {
        // La colonne a une valeur par défaut : la migration ne doit pas
        // transformer le parc existant en chemins réseau.
        $outil = Tool::create([
            'title'          => 'Ancien outil',
            'url'            => 'https://exemple.fr',
            'tool_family_id' => $this->famille->id,
            'is_active'      => true,
            'is_public'      => true,
            'sort_order'     => 1,
        ]);

        $this->assertSame('web', $outil->fresh()->link_type);
        $this->assertFalse($outil->isPath());
    }
}
