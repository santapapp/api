<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvitationStatus;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;

class InvitationController extends Controller
{
    /**
     * Invite a user to the resolved organization.
     */
    public function invite(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'role_name' => 'required|string|in:owner,cashier,kitchen',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $context = app(OrganizationContext::class);
        $organization = $context->get();

        if (!$organization) {
            return response()->json([
                'message' => 'Konteks organisasi wajib resolved sebelum melakukan tindakan ini.',
            ], 500);
        }

        // Cek apakah email sudah terdaftar sebagai member aktif
        $exists = OrganizationMember::where('organization_id', $organization->id)
            ->whereHas('user', function ($query) use ($request) {
                $query->where('email', $request->email);
            })
            ->where('status', MemberStatus::Active)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'User tersebut sudah menjadi member organisasi ini.',
            ], 400);
        }

        // Batalkan undangan pending untuk email yang sama di organisasi ini
        OrganizationInvitation::where('organization_id', $organization->id)
            ->where('email', $request->email)
            ->where('status', InvitationStatus::Pending)
            ->update(['status' => InvitationStatus::Cancelled]);

        // Buat undangan baru
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => $request->email,
            'role_name' => $request->role_name,
            'invited_by' => $request->user()->id,
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'message' => 'Undangan berhasil dibuat.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role_name' => $invitation->role_name,
                'invite_token' => $invitation->invite_token,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Accept an invitation using the invite token.
     */
    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invite_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Cari undangan pending berdasarkan token
        $invitation = OrganizationInvitation::where('invite_token', $request->invite_token)
            ->where('status', InvitationStatus::Pending)
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Undangan tidak ditemukan atau tidak lagi berlaku.',
            ], 404);
        }

        // Cek kedaluwarsa
        if ($invitation->isExpired()) {
            $invitation->update(['status' => InvitationStatus::Expired]);
            return response()->json([
                'message' => 'Undangan ini telah kedaluwarsa.',
            ], 400);
        }

        // Cek apakah email sesuai dengan user yang login
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            return response()->json([
                'message' => 'Undangan ini dikirim untuk alamat email yang berbeda.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($invitation, $user) {
                // 1. Update/buat member
                OrganizationMember::updateOrCreate([
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $user->id,
                ], [
                    'role_name' => $invitation->role_name,
                    'status' => MemberStatus::Active,
                    'joined_at' => now(),
                ]);

                // 2. Assign Spatie role
                app(PermissionRegistrar::class)->setPermissionsTeamId($invitation->organization_id);
                $user->assignRole($invitation->role_name);

                // 3. Update status undangan
                $invitation->update([
                    'status' => InvitationStatus::Accepted,
                    'accepted_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Undangan berhasil diterima. Anda sekarang menjadi member organisasi.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menerima undangan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
