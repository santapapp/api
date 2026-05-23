<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\MemberStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles & permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_register_new_organization_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
                'phone' => '021987654',
                'email' => 'contact@kopisentosa.com',
                'address' => 'Jl. Sentosa No. 10',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('organization.name', 'Kopi Sentosa')
            ->assertJsonPath('organization.slug', 'kopi-sentosa');

        $this->assertDatabaseHas('organizations', [
            'name' => 'Kopi Sentosa',
            'slug' => 'kopi-sentosa',
            'created_by' => $user->id,
        ]);

        $organization = Organization::where('slug', 'kopi-sentosa')->first();

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_name' => 'owner',
            'status' => MemberStatus::Active->value,
        ]);

        // Verify Spatie role was assigned under this team
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $this->assertTrue($user->fresh()->hasRole('owner'));
    }

    public function test_user_can_access_scoped_route_with_valid_organization_context(): void
    {
        $user = User::factory()->create();

        // Register organization
        $orgResponse = $this->actingAs($user)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
            ]);
        $orgUuid = $orgResponse->json('organization.uuid');

        // Access route requiring organization context and specific permission (organization.invite_user)
        $response = $this->actingAs($user)
            ->withHeader('X-Organization-Id', $orgUuid)
            ->postJson(route('api.v1.invitations.invite'), [
                'email' => 'invitee@example.com',
                'role_name' => 'cashier',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('invitation.email', 'invitee@example.com')
            ->assertJsonPath('invitation.role_name', 'cashier');
    }

    public function test_user_cannot_access_scoped_route_without_context_header(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.v1.invitations.invite'), [
                'email' => 'invitee@example.com',
                'role_name' => 'cashier',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Header X-Organization-Id wajib disertakan.',
            ]);
    }

    public function test_non_member_cannot_access_organization_context(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        // Owner creates organization
        $orgResponse = $this->actingAs($owner)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
            ]);
        $orgUuid = $orgResponse->json('organization.uuid');

        // Other user attempts to access using owner's org UUID
        $response = $this->actingAs($otherUser)
            ->withHeader('X-Organization-Id', $orgUuid)
            ->postJson(route('api.v1.invitations.invite'), [
                'email' => 'invitee@example.com',
                'role_name' => 'cashier',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Anda bukan member aktif dari organisasi ini.',
            ]);
    }

    public function test_cannot_access_suspended_organization(): void
    {
        $user = User::factory()->create();

        // Register organization
        $orgResponse = $this->actingAs($user)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
            ]);
        $orgId = $orgResponse->json('organization.id');
        $orgUuid = $orgResponse->json('organization.uuid');

        // Suspend the organization
        Organization::find($orgId)->update([
            'status' => OrganizationStatus::Suspended,
        ]);

        // Attempt access
        $response = $this->actingAs($user)
            ->withHeader('X-Organization-Id', $orgUuid)
            ->postJson(route('api.v1.invitations.invite'), [
                'email' => 'invitee@example.com',
                'role_name' => 'cashier',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Organisasi sedang ditangguhkan atau tidak aktif.',
            ]);
    }
}
