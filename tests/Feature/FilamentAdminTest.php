<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FilamentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles & permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_non_admin_cannot_access_filament_admin_panel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/admin');

        $response->assertStatus(403);
    }

    public function test_administrator_can_access_filament_admin_panel(): void
    {
        $admin = User::factory()->create();

        // Assign global administrator role (team ID is null)
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $admin->assignRole('administrator');

        $response = $this->actingAs($admin)
            ->get('/admin');

        // Should load Filament dashboard
        $response->assertStatus(200);

        // Test resource list pages to ensure no compile or class-not-found errors (such as Table Actions errors)
        $this->actingAs($admin)->get('/admin/users')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/organizations')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/organization-members')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/activity-logs')->assertStatus(200);
    }
}
