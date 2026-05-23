<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\InvitationStatus;
use App\Enums\MemberStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles & permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_owner_can_invite_a_new_member(): void
    {
        $owner = User::factory()->create();

        // Register organization
        $orgResponse = $this->actingAs($owner)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
            ]);
        $orgUuid = $orgResponse->json('organization.uuid');

        // Create invitation
        $response = $this->actingAs($owner)
            ->withHeader('X-Organization-Id', $orgUuid)
            ->postJson(route('api.v1.invitations.invite'), [
                'email' => 'cashier@example.com',
                'role_name' => 'cashier',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'invitation' => ['id', 'email', 'role_name', 'invite_token', 'expires_at'],
            ]);

        $this->assertDatabaseHas('organization_invitations', [
            'email' => 'cashier@example.com',
            'role_name' => 'cashier',
            'status' => InvitationStatus::Pending->value,
        ]);
    }

    public function test_user_can_accept_valid_invitation(): void
    {
        $owner = User::factory()->create();

        // 1. Owner registers organization
        $orgResponse = $this->actingAs($owner)
            ->postJson(route('api.v1.organizations.store'), [
                'name' => 'Kopi Sentosa',
                'slug' => 'kopi-sentosa',
            ]);
        $orgId = $orgResponse->json('organization.id');
        $orgUuid = $orgResponse->json('organization.uuid');

        // 2. Owner invites target user
        $invitee = User::factory()->create(['email' => 'cashier@example.com']);

        $inviteResponse = $this->actingAs($owner)
            ->withHeader('X-Organization-Id', $orgUuid)
            ->postJson(route('api.v1.invitations.invite'), [
                'email' => 'cashier@example.com',
                'role_name' => 'cashier',
            ]);
        $inviteToken = $inviteResponse->json('invitation.invite_token');

        // 3. Target user accepts the invitation
        $acceptResponse = $this->actingAs($invitee)
            ->postJson(route('api.v1.invitations.accept'), [
                'invite_token' => $inviteToken,
            ]);

        $acceptResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Undangan berhasil diterima. Anda sekarang menjadi member organisasi.',
            ]);

        // Verify membership record exists
        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $orgId,
            'user_id' => $invitee->id,
            'role_name' => 'cashier',
            'status' => MemberStatus::Active->value,
        ]);

        // Verify invitation status is accepted
        $this->assertDatabaseHas('organization_invitations', [
            'invite_token' => $inviteToken,
            'status' => InvitationStatus::Accepted->value,
        ]);

        // Verify role assignment scoped to organization
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($orgId);
        $this->assertTrue($invitee->fresh()->hasRole('cashier'));
    }

    public function test_user_cannot_accept_expired_invitation(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create(['created_by' => $owner->id]);

        $invitee = User::factory()->create(['email' => 'expired@example.com']);

        // Create an expired invitation
        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'expired@example.com',
            'role_name' => 'cashier',
            'invited_by' => $owner->id,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->subHour(), // Expired 1 hour ago
        ]);

        $response = $this->actingAs($invitee)
            ->postJson(route('api.v1.invitations.accept'), [
                'invite_token' => $invitation->invite_token,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Undangan ini telah kedaluwarsa.',
            ]);

        $this->assertEquals(InvitationStatus::Expired, $invitation->fresh()->status);
    }

    public function test_user_cannot_accept_invitation_with_different_email(): void
    {
        $owner = User::factory()->create();
        $org = Organization::factory()->create(['created_by' => $owner->id]);

        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);

        // Create invitation for a different email
        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => 'correct@example.com',
            'role_name' => 'cashier',
            'invited_by' => $owner->id,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($wrongUser)
            ->postJson(route('api.v1.invitations.accept'), [
                'invite_token' => $invitation->invite_token,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Undangan ini dikirim untuk alamat email yang berbeda.',
            ]);
    }
}
