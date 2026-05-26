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

class DiningTableController extends Controller
{
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

    public function destroy(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $table = DiningTable::where('organization_id', $orgId)->findOrFail($id);
        $table->delete();

        return response()->json(['message' => 'Meja berhasil dihapus.']);
    }

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
