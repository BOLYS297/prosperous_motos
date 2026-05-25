<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_profile()
    {
        // Crée un super admin
        $admin = User::factory()->create([
            'nom_utilisateur' => 'admin1',
            'email' => 'admin1@example.com',
            'role' => 'super_admin',
            'password' => bcrypt('secret123')
        ]);

        $this->actingAs($admin);

        // Accéder à la page d'édition
        $response = $this->get(route('admin.profile.edit'));
        $response->assertStatus(200);
        $response->assertSee('Mon profil');

        // Envoyer une mise à jour
        $response = $this->followingRedirects()->put(route('admin.profile.update'), [
            'nom_utilisateur' => 'admin-updated',
            'email' => 'admin-updated@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'nom_utilisateur' => 'admin-updated',
            'email' => 'admin-updated@example.com',
        ]);
    }
}
