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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\PermissionRegistrar;

class OrganizationController extends Controller
{
    /**
     * Create/Register a new organization.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug',
            'phone' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'province' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Ambil konfigurasi default
        $config = config('santap.organization');

        try {
            $organization = DB::transaction(function () use ($request, $user, $config) {
                // 1. Buat organisasi baru
                $organization = Organization::create([
                    'name' => $request->name,
                    'slug' => $request->slug,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'address' => $request->address,
                    'city' => $request->city,
                    'province' => $request->province,
                    'country' => $config['default_country'] ?? 'ID',
                    'timezone' => $config['default_timezone'] ?? 'Asia/Jakarta',
                    'currency' => $config['default_currency'] ?? 'IDR',
                    'status' => OrganizationStatus::Active,
                    'created_by' => $user->id,
                ]);

                // 2. Tambahkan user sebagai member aktif dengan role owner
                OrganizationMember::create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role_name' => 'owner',
                    'status' => MemberStatus::Active,
                    'joined_at' => now(),
                ]);

                // 3. Assign Spatie owner role untuk organisasi ini
                app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
                $user->assignRole('owner');

                return $organization;
            });

            return response()->json([
                'message' => 'Organisasi berhasil didaftarkan.',
                'organization' => [
                    'id' => $organization->id,
                    'uuid' => $organization->uuid,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mendaftarkan organisasi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
