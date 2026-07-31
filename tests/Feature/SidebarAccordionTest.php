<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarAccordionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_section_outils_est_repliable(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/');

        $response->assertSee('data-sidebar-section="tools"', false);
        $response->assertSee('data-sidebar-toggle="tools"', false);
        $response->assertSee('aria-controls="sidebar-section-tools"', false);
        $response->assertSee('id="sidebar-section-tools"', false);
    }

    public function test_la_section_administration_n_apparait_que_pour_un_admin(): void
    {
        $user  = User::factory()->create(['is_active' => true, 'role' => 'user']);
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        $this->actingAs($user)->get('/')
            ->assertDontSee('data-sidebar-toggle="admin"', false);

        $this->actingAs($admin)->get('/')
            ->assertSee('data-sidebar-toggle="admin"', false);
    }

    public function test_la_section_de_la_page_courante_est_transmise_au_script_d_amorcage(): void
    {
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin']);

        // Sur une page d'outil, c'est la section « tools » qui doit être rouverte.
        $this->actingAs($admin)->get('/tools/qr-code')
            ->assertSee('var active = "tools";', false);

        // Sur une page d'administration, la section « admin ».
        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('var active = "admin";', false);

        // Ailleurs, aucune section n'est forcée.
        $this->actingAs($admin)->get('/')
            ->assertSee('var active = null;', false);
    }
}
