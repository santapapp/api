<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Requests\Organization\UploadBannerRequest;
use App\Http\Requests\Organization\UploadLogoRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Services\MediaService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @tags Mobile Organization
 */
class OrganizationController extends Controller
{
    /**
     * Daftar organisasi yang diikuti user yang sedang login.
     *
     * Setiap organisasi menyertakan `role` user pada organisasi tersebut
     * (owner / cashier / kitchen) dari tabel `organization_members`.
     */
    public function index(Request $request): JsonResponse
    {
        $orgs = $request->user()
            ->organizations()
            ->get();

        return response()->json([
            'data' => OrganizationResource::collection($orgs),
        ]);
    }

    /**
     * Buat organisasi baru. User pembuat otomatis menjadi `owner`.
     */
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
     * Detail organisasi aktif.
     *
     * Organisasi ditentukan lewat header `X-Org-ID`.
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
     * Update pengaturan organisasi aktif (header `X-Org-ID`).
     *
     * Hanya member dengan role `owner` yang diizinkan.
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

    /**
     * Upload logo organisasi aktif (multipart/form-data). Hanya owner.
     *
     * Field file: `logo` (jpeg/jpg/png/webp, maks 2 MB). Logo lama otomatis
     * dihapus. Mengembalikan organisasi dengan `logo` berisi URL siap pakai.
     */
    public function uploadLogo(UploadLogoRequest $request, MediaService $media): JsonResponse
    {
        $org = $this->resolveOwnedOrganization($request, 'logo');

        if ($org instanceof JsonResponse) {
            return $org;
        }

        $path = $media->replace($request->file('logo'), 'organization/logos', $org->logo);
        $org->update(['logo' => $path]);

        return response()->json([
            'data'    => new OrganizationResource($org->fresh()),
            'message' => 'Logo organisasi berhasil diunggah.',
        ]);
    }

    /**
     * Upload banner organisasi aktif (multipart/form-data). Hanya owner.
     *
     * Field file: `banner` (jpeg/jpg/png/webp, maks 2 MB). Banner lama otomatis
     * dihapus. Mengembalikan organisasi dengan `banner` berisi URL siap pakai.
     */
    public function uploadBanner(UploadBannerRequest $request, MediaService $media): JsonResponse
    {
        $org = $this->resolveOwnedOrganization($request, 'banner');

        if ($org instanceof JsonResponse) {
            return $org;
        }

        $path = $media->replace($request->file('banner'), 'organization/banners', $org->banner);
        $org->update(['banner' => $path]);

        return response()->json([
            'data'    => new OrganizationResource($org->fresh()),
            'message' => 'Banner organisasi berhasil diunggah.',
        ]);
    }

    /**
     * Resolusi organisasi aktif + gate owner. Mengembalikan Organization bila
     * diizinkan, atau JsonResponse 403 bila bukan owner.
     */
    private function resolveOwnedOrganization(Request $request, string $what): Organization|JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $org   = Organization::findOrFail($orgId);

        $member = OrganizationMember::where('organization_id', $orgId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($member->role !== 'owner') {
            return response()->json(['message' => "Hanya owner yang dapat mengubah {$what} organisasi."], 403);
        }

        return $org;
    }
}
