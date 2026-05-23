<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemberStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContextController extends Controller
{
    /**
     * Switch context by validating membership and returning the organization's details and user role.
     */
    public function switchOrganization(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|string', // Bisa berupa integer ID atau UUID string
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $orgIdentifier = $request->organization_id;

        // Cari organisasi
        $organization = Organization::where('uuid', $orgIdentifier)
            ->orWhere('id', is_numeric($orgIdentifier) ? (int) $orgIdentifier : 0)
            ->first();

        if (!$organization) {
            return response()->json([
                'message' => 'Organisasi tidak ditemukan.',
            ], 404);
        }

        if ($organization->status !== OrganizationStatus::Active) {
            return response()->json([
                'message' => 'Organisasi sedang ditangguhkan atau tidak aktif.',
            ], 403);
        }

        // Cek membership user
        $member = OrganizationMember::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member || $member->status !== MemberStatus::Active) {
            return response()->json([
                'message' => 'Anda bukan member aktif dari organisasi ini.',
            ], 403);
        }

        return response()->json([
            'message' => 'Konteks organisasi berhasil diverifikasi.',
            'organization' => [
                'id' => $organization->id,
                'uuid' => $organization->uuid,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'role' => $member->role_name,
        ]);
    }
}
