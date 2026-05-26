<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgs = $request->user()
            ->organizations()
            ->get();

        return response()->json([
            'data' => OrganizationResource::collection($orgs),
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $org = Organization::create([
            'name' => $request->name,
            'slug' => Str::lower($request->slug),
        ]);

        // User otomatis jadi owner
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id'         => $request->user()->id,
            'role'            => 'owner',
        ]);

        return response()->json([
            'data'    => new OrganizationResource($org->fresh()),
            'message' => 'Organisasi berhasil dibuat.',
        ], 201);
    }

    /**
     * Detail organisasi aktif (via X-Org-ID header).
     */
    public function show(): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $org   = Organization::findOrFail($orgId);

        return response()->json([
            'data' => new OrganizationResource($org),
        ]);
    }

    /**
     * Update settings organisasi aktif.
     * Hanya owner yang boleh akses — role check dilakukan via middleware / gate.
     */
    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $org   = Organization::findOrFail($orgId);

        // Role gate: hanya owner
        $member = OrganizationMember::where('organization_id', $orgId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($member->role !== 'owner') {
            return response()->json(['message' => 'Hanya owner yang dapat mengubah pengaturan organisasi.'], 403);
        }

        $org->update($request->validated());

        return response()->json([
            'data'    => new OrganizationResource($org->fresh()),
            'message' => 'Pengaturan organisasi berhasil diupdate.',
        ]);
    }
}
