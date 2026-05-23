<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\QrCodeStatus;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\TableQrCode;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DiningTableController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = DiningTable::with(['qrCode' => function ($query) {
            $query->where('status', QrCodeStatus::Active);
        }])->orderBy('code')->get()->map(fn ($table) => $this->transformTable($table));

        return response()->json([
            'data' => $tables,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:available,occupied,reserved,inactive',
            'location_label' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek keunikan code per organisasi
        $exists = DiningTable::where('code', $request->code)->exists();
        if ($exists) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => ['code' => ['Kode meja ini sudah digunakan di organisasi Anda.']],
            ], 422);
        }

        $organization = app(OrganizationContext::class)->get();

        $table = DB::transaction(function () use ($request, $organization) {
            // 1. Create table
            $table = DiningTable::create([
                'name' => $request->name,
                'code' => $request->code,
                'capacity' => $request->capacity ?? 2,
                'status' => $request->status ?? 'available',
                'location_label' => $request->location_label,
            ]);

            // 2. Generate QR
            $token = Str::random(32);
            $qrUrl = "https://santap.id/o/{$organization->slug}/t/{$table->code}?qr={$token}";

            TableQrCode::create([
                'dining_table_id' => $table->id,
                'qr_token' => $token,
                'qr_url' => $qrUrl,
                'status' => QrCodeStatus::Active,
            ]);

            return $table;
        });

        // Eager load active qr
        $table->load(['qrCode' => fn ($q) => $q->where('status', QrCodeStatus::Active)]);

        return response()->json([
            'message' => 'Meja makan berhasil dibuat.',
            'data' => $this->transformTable($table),
        ], 201);
    }

    public function show(int $diningTable): JsonResponse
    {
        $context = app(OrganizationContext::class);
        $table = DiningTable::where('id', $diningTable)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$table) {
            return response()->json(['message' => 'Meja tidak ditemukan.'], 404);
        }

        $table->load(['qrCode' => fn ($q) => $q->where('status', QrCodeStatus::Active)]);

        return response()->json([
            'data' => $this->transformTable($table),
        ]);
    }

    public function update(Request $request, int $diningTable): JsonResponse
    {
        $context = app(OrganizationContext::class);
        $diningTable = DiningTable::where('id', $diningTable)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$diningTable) {
            return response()->json(['message' => 'Meja tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'status' => 'required|string|in:available,occupied,reserved,inactive',
            'location_label' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Cek keunikan code per organisasi
        if ($request->code !== $diningTable->code) {
            $exists = DiningTable::where('code', $request->code)
                ->where('id', '!=', $diningTable->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => ['code' => ['Kode meja ini sudah digunakan di organisasi Anda.']],
                ], 422);
            }
        }

        $organization = app(OrganizationContext::class)->get();

        DB::transaction(function () use ($request, $diningTable, $organization) {
            $codeChanged = $request->code !== $diningTable->code;

            $diningTable->update([
                'name' => $request->name,
                'code' => $request->code,
                'capacity' => $request->capacity,
                'status' => $request->status,
                'location_label' => $request->location_label,
            ]);

            // Jika kode meja berubah, update url QR aktif jika ada
            if ($codeChanged) {
                $activeQr = TableQrCode::where('dining_table_id', $diningTable->id)
                    ->where('status', QrCodeStatus::Active)
                    ->first();
                if ($activeQr) {
                    $activeQr->update([
                        'qr_url' => "https://santap.id/o/{$organization->slug}/t/{$diningTable->code}?qr={$activeQr->qr_token}"
                    ]);
                }
            }
        });

        $diningTable->load(['qrCode' => fn ($q) => $q->where('status', QrCodeStatus::Active)]);

        return response()->json([
            'message' => 'Meja makan berhasil diperbarui.',
            'data' => $this->transformTable($diningTable),
        ]);
    }

    public function destroy(int $diningTable): JsonResponse
    {
        $context = app(OrganizationContext::class);
        $table = DiningTable::where('id', $diningTable)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$table) {
            return response()->json(['message' => 'Meja tidak ditemukan.'], 404);
        }

        $table->delete();

        return response()->json([
            'message' => 'Meja makan berhasil dihapus.',
        ]);
    }

    public function regenerateQr(int $diningTable): JsonResponse
    {
        $context = app(OrganizationContext::class);
        $organization = $context->get();
        $diningTable = DiningTable::where('id', $diningTable)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$diningTable) {
            return response()->json(['message' => 'Meja tidak ditemukan.'], 404);
        }

        DB::transaction(function () use ($diningTable, $organization) {
            // Revoke old QRs
            TableQrCode::where('dining_table_id', $diningTable->id)
                ->where('status', QrCodeStatus::Active)
                ->update(['status' => QrCodeStatus::Revoked]);

            // Generate new QR
            $token = Str::random(32);
            $qrUrl = "https://santap.id/o/{$organization->slug}/t/{$diningTable->code}?qr={$token}";

            TableQrCode::create([
                'dining_table_id' => $diningTable->id,
                'qr_token' => $token,
                'qr_url' => $qrUrl,
                'status' => QrCodeStatus::Active,
            ]);
        });

        $diningTable->load(['qrCode' => fn ($q) => $q->where('status', QrCodeStatus::Active)]);

        return response()->json([
            'message' => 'QR Code berhasil diregenerasi.',
            'data' => $this->transformTable($diningTable),
        ]);
    }

    private function transformTable(DiningTable $table): array
    {
        $activeQr = $table->qrCode;

        return [
            'id' => $table->id,
            'organization_id' => $table->organization_id,
            'name' => $table->name,
            'code' => $table->code,
            'capacity' => $table->capacity,
            'status' => $table->status,
            'location_label' => $table->location_label,
            'qr_token' => $activeQr?->qr_token,
            'qr_url' => $activeQr?->qr_url,
            'created_at' => $table->created_at,
            'updated_at' => $table->updated_at,
        ];
    }
}
