<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiningTable\StoreDiningTableRequest;
use App\Http\Requests\DiningTable\UpdateDiningTableRequest;
use App\Http\Resources\DiningTableResource;
use App\Models\DiningTable;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Pengelolaan meja (`dining_tables`) per organisasi lewat header `X-Org-ID`.
 * Setiap meja punya `qr_token` untuk dipindai pelanggan.
 *
 * @tags Mobile Table
 */
class DiningTableController extends Controller
{
    /**
     * List semua meja pada organisasi aktif.
     */
    public function index(): JsonResponse
    {
        $orgId  = app(OrganizationContext::class)->getOrganizationId();
        $tables = DiningTable::where('organization_id', $orgId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => DiningTableResource::collection($tables),
        ]);
    }

    /**
     * Buat meja baru. `qr_token` digenerate otomatis.
     */
    public function store(StoreDiningTableRequest $request): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $table = DiningTable::create([
            'organization_id' => $orgId,
            'name'            => $request->name,
            'code'            => $request->code,
            'capacity'        => $request->capacity,
            'location'        => $request->location,
            'qr_token'        => Str::random(32),
            'metadata'        => $request->metadata,
        ]);

        return response()->json([
            'data'    => new DiningTableResource($table->fresh()),
            'message' => 'Meja berhasil dibuat.',
        ], 201);
    }

    /**
     * Update data meja.
     */
    public function update(UpdateDiningTableRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $table = DiningTable::where('organization_id', $orgId)->findOrFail($id);

        $table->update($request->validated());

        return response()->json([
            'data'    => new DiningTableResource($table->fresh()),
            'message' => 'Meja berhasil diupdate.',
        ]);
    }

    /**
     * Hapus meja.
     */
    public function destroy(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $table = DiningTable::where('organization_id', $orgId)->findOrFail($id);
        $table->delete();

        return response()->json(['message' => 'Meja berhasil dihapus.']);
    }

    /**
     * Regenerate `qr_token` meja (token lama otomatis tidak berlaku).
     */
    public function regenerateQr(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $table = DiningTable::where('organization_id', $orgId)->findOrFail($id);
        $table->update(['qr_token' => Str::random(32)]);

        return response()->json([
            'data'    => new DiningTableResource($table->fresh()),
            'message' => 'QR token berhasil di-regenerate.',
        ]);
    }
}
