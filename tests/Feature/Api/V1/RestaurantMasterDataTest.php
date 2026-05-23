<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Organization;
use App\Models\User;
use App\Enums\MenuStatus;
use App\Enums\TableStatus;
use App\Enums\QrCodeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RestaurantMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $otherOwner;
    protected Organization $orgA;
    protected Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Buat 2 Organisasi
        $this->owner = User::factory()->create();
        $this->otherOwner = User::factory()->create();

        $this->orgA = Organization::factory()->create(['name' => 'Org A', 'slug' => 'org-a', 'created_by' => $this->owner->id]);
        $this->orgB = Organization::factory()->create(['name' => 'Org B', 'slug' => 'org-b', 'created_by' => $this->otherOwner->id]);

        // Gabungkan member & role di Spatie
        $this->orgA->users()->attach($this->owner->id, ['role_name' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $this->orgB->users()->attach($this->otherOwner->id, ['role_name' => 'owner', 'status' => 'active', 'joined_at' => now()]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);
        $this->owner->assignRole('owner');

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgB->id);
        $this->otherOwner->assignRole('owner');
    }

    public function test_owner_can_manage_menu_categories(): void
    {
        // 1. Create Category
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->postJson('/api/v1/menu-categories', [
                'name' => 'Minuman Es',
                'description' => 'Semua kategori es segar',
                'sort_order' => 1,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Minuman Es');
        $categoryId = $response->json('data.id');

        // 2. Read Categories
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->getJson('/api/v1/menu-categories');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        // 3. Update Category
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->putJson("/api/v1/menu-categories/{$categoryId}", [
                'name' => 'Minuman Hangat',
                'description' => 'Kopi hangat',
                'sort_order' => 2,
                'status' => 'active',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Minuman Hangat');

        // 4. Delete Category
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->deleteJson("/api/v1/menu-categories/{$categoryId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('menu_categories', ['id' => $categoryId]);
    }

    public function test_menu_category_is_scoped_by_organization(): void
    {
        // Owner Org A membuat kategori
        $categoryA = MenuCategory::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Makanan Utama',
            'slug' => 'makanan-utama',
            'status' => 'active',
        ]);

        // Owner Org B mencoba melihat kategori Org A
        $response = $this->actingAs($this->otherOwner)
            ->withHeader('X-Organization-Id', (string) $this->orgB->id)
            ->getJson("/api/v1/menu-categories/{$categoryA->id}");

        // Ditolak / 404 karena Global Scope BelongsToOrganization membatasi query
        $response->assertStatus(404);
    }

    public function test_owner_can_manage_menus(): void
    {
        $category = MenuCategory::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Kopi',
            'slug' => 'kopi',
            'status' => 'active',
        ]);

        // 1. Create Menu
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->postJson('/api/v1/menus', [
                'menu_category_id' => $category->id,
                'name' => 'Espresso',
                'description' => 'Kopi murni pekat',
                'price' => 15000,
                'sku' => 'ESP-01',
                'sort_order' => 1,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Espresso');
        $menuId = $response->json('data.id');

        // 2. Read Menus
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->getJson('/api/v1/menus');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        // 3. Update Menu
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->putJson("/api/v1/menus/{$menuId}", [
                'menu_category_id' => $category->id,
                'name' => 'Double Espresso',
                'description' => 'Kopi murni double pekat',
                'price' => 20000,
                'sku' => 'ESP-02',
                'status' => 'active',
                'sort_order' => 1,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Double Espresso');
        $response->assertJsonPath('data.price', '20000.00');

        // 4. Delete Menu
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->deleteJson("/api/v1/menus/{$menuId}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('menus', ['id' => $menuId]);
    }

    public function test_owner_can_manage_dining_tables_and_generate_qr(): void
    {
        // 1. Create Dining Table
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->postJson('/api/v1/dining-tables', [
                'name' => 'Meja Teras 01',
                'code' => 'T01',
                'capacity' => 4,
                'location_label' => 'Teras Luar',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'T01');
        $response->assertJsonPath('data.qr_url', "https://santap.id/o/org-a/t/T01?qr=" . $response->json('data.qr_token'));

        $tableId = $response->json('data.id');
        $oldQrToken = $response->json('data.qr_token');

        // Pastikan QR code tercatat di database dengan status active
        $this->assertDatabaseHas('table_qr_codes', [
            'dining_table_id' => $tableId,
            'qr_token' => $oldQrToken,
            'status' => QrCodeStatus::Active->value,
        ]);

        // 2. Regenerate QR
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Organization-Id', (string) $this->orgA->id)
            ->postJson("/api/v1/dining-tables/{$tableId}/regenerate-qr");

        $response->assertStatus(200);
        $newQrToken = $response->json('data.qr_token');
        $this->assertNotEquals($oldQrToken, $newQrToken);

        // Pastikan QR lama ter-revoke
        $this->assertDatabaseHas('table_qr_codes', [
            'dining_table_id' => $tableId,
            'qr_token' => $oldQrToken,
            'status' => QrCodeStatus::Revoked->value,
        ]);

        // Pastikan QR baru active
        $this->assertDatabaseHas('table_qr_codes', [
            'dining_table_id' => $tableId,
            'qr_token' => $newQrToken,
            'status' => QrCodeStatus::Active->value,
        ]);
    }
}
