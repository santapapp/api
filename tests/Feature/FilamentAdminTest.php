<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
        $admin = User::factory()->create([
            'is_superadmin' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin');

        // Should load Filament dashboard
        $response->assertStatus(200);

        // Test resource list pages to ensure no compile or class-not-found errors (such as Table Actions errors)
        $this->actingAs($admin)->get('/admin/users')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/organizations')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/dining-tables')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/menus')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/orders')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/orders/create')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/orders/create?order_type=open_bill')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/open-bill-sessions')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/qris-payments')->assertStatus(200);
    }
}
