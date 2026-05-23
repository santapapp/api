<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\CustomerSessionStatus;
use App\Enums\OrganizationStatus;
use App\Enums\QrCodeStatus;
use App\Enums\TableStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\OpenBill;
use App\Models\Organization;
use App\Models\TableQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerSessionController extends Controller
{
    /**
     * Start a guest customer session via scanning a table QR code.
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_slug' => 'required|string|exists:organizations,slug',
            'table_code' => 'required|string|exists:dining_tables,code',
            'qr_token' => 'required|string',
            'client_label' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 1. Validasi Organisasi
        $organization = Organization::where('slug', $request->organization_slug)
            ->where('status', OrganizationStatus::Active)
            ->first();

        if (!$organization) {
            return response()->json([
                'message' => 'Organisasi tidak ditemukan atau sedang tidak aktif.',
            ], 404);
        }

        // 2. Validasi Meja
        $table = DiningTable::where('organization_id', $organization->id)
            ->where('code', $request->table_code)
            ->first();

        if (!$table || $table->status === TableStatus::Inactive) {
            return response()->json([
                'message' => 'Meja makan tidak ditemukan atau tidak tersedia.',
            ], 404);
        }

        // 3. Validasi Token QR
        $qrCode = TableQrCode::where('dining_table_id', $table->id)
            ->where('qr_token', $request->qr_token)
            ->where('status', QrCodeStatus::Active)
            ->first();

        if (!$qrCode) {
            return response()->json([
                'message' => 'Kode QR tidak valid atau telah kedaluwarsa.',
            ], 400);
        }

        // 4. Proses Pembuatan Sesi Pelanggan & Tagihan (Bill)
        $session = DB::transaction(function () use ($request, $organization, $table) {
            // Temukan atau buat open bill aktif untuk meja ini
            $bill = OpenBill::where('dining_table_id', $table->id)
                ->where('status', BillStatus::Open)
                ->first();

            if (!$bill) {
                $billNumber = 'BILL-' . now()->format('YmdHis') . '-' . Str::upper($table->code);
                $bill = OpenBill::create([
                    'organization_id' => $organization->id,
                    'dining_table_id' => $table->id,
                    'bill_number' => $billNumber,
                    'status' => BillStatus::Open,
                    'subtotal_amount' => 0.00,
                    'discount_amount' => 0.00,
                    'service_amount' => 0.00,
                    'tax_amount' => 0.00,
                    'total_amount' => 0.00,
                    'opened_at' => now(),
                ]);
            }

            // Update status meja menjadi occupied
            $table->update(['status' => TableStatus::Occupied]);

            // Buat sesi pelanggan baru (berlaku 4 jam)
            $token = 'cust_sess_' . Str::random(40);
            return CustomerSession::create([
                'organization_id' => $organization->id,
                'dining_table_id' => $table->id,
                'open_bill_id' => $bill->id,
                'session_token' => $token,
                'client_label' => $request->client_label,
                'status' => CustomerSessionStatus::Active,
                'started_at' => now(),
                'expires_at' => now()->addHours(4),
            ]);
        });

        // Load relasi
        $session->load(['table', 'organization', 'bill']);

        return response()->json([
            'message' => 'Sesi pelanggan berhasil dimulai.',
            'session_token' => $session->session_token,
            'expires_at' => $session->expires_at->toIso8601String(),
            'organization' => [
                'id' => $session->organization->id,
                'uuid' => $session->organization->uuid,
                'name' => $session->organization->name,
                'slug' => $session->organization->slug,
            ],
            'table' => [
                'id' => $session->table->id,
                'name' => $session->table->name,
                'code' => $session->table->code,
            ],
            'open_bill' => [
                'id' => $session->bill->id,
                'bill_number' => $session->bill->bill_number,
                'status' => $session->bill->status,
                'total_amount' => $session->bill->total_amount,
            ],
        ], 200);
    }

    /**
     * Get the current active customer session.
     */
    public function current(Request $request): JsonResponse
    {
        $session = $request->attributes->get('customer_session');
        $session->load(['table', 'organization', 'bill']);

        return response()->json([
            'data' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'client_label' => $session->client_label,
                'status' => $session->status,
                'expires_at' => $session->expires_at->toIso8601String(),
                'table' => [
                    'id' => $session->table->id,
                    'name' => $session->table->name,
                    'code' => $session->table->code,
                ],
                'open_bill' => $session->bill ? [
                    'id' => $session->bill->id,
                    'bill_number' => $session->bill->bill_number,
                    'status' => $session->bill->status,
                    'total_amount' => $session->bill->total_amount,
                ] : null,
            ],
        ]);
    }
}
