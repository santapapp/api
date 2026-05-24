<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiningTableController extends Controller
{
    public function index(): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $tables = DiningTable::where('organization_id', $orgId)
            ->orderBy('name')
            ->get()
            ->map(fn (DiningTable $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'qr_token' => $t->qr_token,
                'is_active' => $t->is_active,
                'has_active_order' => $t->activeOrder()->exists(),
            ]);

        return response()->json(['data' => $tables]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $table = DiningTable::create([
            'organization_id' => $orgId,
            'name' => $request->name,
            'qr_token' => Str::random(32),
        ]);

        return response()->json([
            'data' => [
                'id' => $table->id,
                'name' => $table->name,
                'qr_token' => $table->qr_token,
            ],
            'message' => 'Meja berhasil dibuat.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $table = DiningTable::where('organization_id', $orgId)->findOrFail($id);

        $table->update(['name' => $request->name]);

        return response()->json([
            'data' => [
                'id' => $table->id,
                'name' => $table->name,
                'qr_token' => $table->qr_token,
            ],
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
            'data' => [
                'id' => $table->id,
                'name' => $table->name,
                'qr_token' => $table->qr_token,
            ],
            'message' => 'QR token berhasil di-regenerate.',
        ]);
    }
}
