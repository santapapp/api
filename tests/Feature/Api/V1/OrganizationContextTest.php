<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_new_organization_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Kopi Sentosa')
            ->assertJsonPath('data.slug', 'kopi-sentosa');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Kopi Sentosa',
            'slug' => 'kopi-sentosa',
        ]);

        $organization = Organization::where('slug', 'kopi-sentosa')->first();

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_access_scoped_route_with_valid_organization_context(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create([
            'is_active' => 'true',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Org-ID', (string) $org->id)
            ->getJson(route('api.v1.dining-tables.index'));

        $response->assertStatus(200);
    }

    public function test_user_cannot_access_scoped_route_without_context_header(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.v1.dining-tables.index'));

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Header X-Org-ID wajib disertakan.',
            ]);
    }

    public function test_non_member_cannot_access_organization_context(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $org = Organization::factory()->create([
            'is_active' => 'true',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($otherUser)
            ->withHeader('X-Org-ID', (string) $org->id)
            ->getJson(route('api.v1.dining-tables.index'));

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Anda bukan member dari organisasi ini.',
            ]);
    }

    public function test_cannot_access_suspended_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create([
            'is_active' => 'false',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Org-ID', (string) $org->id)
            ->getJson(route('api.v1.dining-tables.index'));

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Organisasi tidak aktif.',
            ]);
    }
}
