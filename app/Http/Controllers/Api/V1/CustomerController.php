<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddItemsRequest;
use App\Http\Resources\MenuResource;
use App\Http\Resources\OrderDetailResource;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Organization;
use App\Services\OrderItemService;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @tags Customer Web
 */
class CustomerController extends Controller
{
    /**
     * Data publik organisasi berdasarkan slug.
     */
    public function organization(string $slug): JsonResponse
    {
        $org = Organization::where('slug', $slug)
            ->where('is_active', true)   // fix: boolean bukan string
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id'       => $org->id,
                'name'     => $org->name,
                'slug'     => $org->slug,
                'phone'    => $org->phone,
                'address'  => $org->address,
                'city'     => $org->city,
                'logo'     => $org->logo,
                'timezone' => $org->timezone,
                'currency' => $org->currency,
                'opening_hours' => $org->opening_hours,
            ],
        ]);
    }

    /**
     * Scan QR meja → dapatkan public_token.
     * Buat order baru jika belum ada, dengan snapshot tax/service.
     */
    public function scanTable(string $qrToken): JsonResponse
    {
        $table = DiningTable::where('qr_token', $qrToken)
            ->where('is_active', true)   // fix: boolean bukan string
            ->firstOrFail();

        return response()->json([
            'data' => [
                'organization' => [
                    'id'   => $table->organization->id,
                    'name' => $table->organization->name,
                    'slug' => $table->organization->slug,
                ],
                'table' => [
                    'id'       => $table->id,
                    'name'     => $table->name,
                    'code'     => $table->code,
                    'location' => $table->location,
                ],
            ],
        ]);
    }

    /**
     * Create order dari direct table order.
     * Mengembalikan public_token yang akan dipakai di halaman payments.
     */
    public function createOrder(\App\Http\Requests\Customer\CreateOrderRequest $request, OrderItemService $service, QrisService $qris): JsonResponse
    {
        $table = DiningTable::where('qr_token', $request->validated('qr_token'))
            ->where('is_active', true)
            ->firstOrFail();

        $org = Organization::findOrFail($table->organization_id);
        $taxSnapshot     = $org->tax_enabled ? (float) $org->tax_rate : 0.0;
        $serviceSnapshot = $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0;

        try {
            $response = \Illuminate\Support\Facades\DB::transaction(function () use ($table, $taxSnapshot, $serviceSnapshot, $request, $service, $qris) {
                $order = Order::create([
                    'order_number'                 => Order::generateOrderNumber($table->organization_id),
                    'public_token'                 => Str::random(32),
                    'organization_id'              => $table->organization_id,
                    'dining_table_id'              => $table->id,
                    'order_type'                   => OrderType::TableOrder,
                    'bill_status'                  => BillStatus::Open,
                    'order_status'                 => OrderStatus::Pending,
                    'payment_status'               => PaymentStatus::Pending,
                    'payment_method'               => 'qris',
                    'tax_rate_snapshot'            => $taxSnapshot,
                    'service_charge_rate_snapshot' => $serviceSnapshot,
                    'subtotal_amount'              => 0,
                    'discount_amount'              => 0,
                    'tax_amount'                   => 0,
                    'service_charge_amount'        => 0,
                    'total_amount'                 => 0,
                    'payment_amount'               => 0,
                    'change_amount'                => 0,
                    'opened_at'                    => now(),
                    'payment_expires_at'           => now()->addMinutes(5),
                ]);

                // Add items (also recalculates totals)
                $service->addItems($order, $request->validated('items'));
                
                $order->refresh();

                // Generate QRIS
                $reference = "santap-{$order->id}";
                $result    = $qris->create($reference, (float) $order->total_amount);

                $order->update([
                    'payment_reference' => $reference,
                ]);

                return [
                    'order' => $order,
                    'qris_data' => [
                        'qr_url' => $result['qr_url'] ?? null,
                        'payment_reference' => $reference,
                    ],
                ];
            });

            $order = $response['order'];

            return response()->json([
                'data' => [
                    'order_id'           => $order->id,
                    'order_no'           => $order->order_number,
                    'payment_status'     => $order->payment_status->value,
                    'order_status'       => $order->order_status->value,
                    'qris_data'          => $response['qris_data'],
                    'payment_expires_at' => $order->payment_expires_at->toIso8601String(),
                    'server_time'        => now()->toIso8601String(),
                    'public_token'       => $order->public_token,
                ],
                'message' => 'Pesanan berhasil dibuat dan QRIS siap.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat pesanan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menu publik (tersedia saja, grouped by tree).
     */
    public function menu(Request $request): JsonResponse
    {
        $request->validate([
            'org' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $menus = Menu::where('organization_id', $request->org)
            ->products()
            ->available()
            ->with(['children' => function ($q) {
                $q->where('is_available', true)
                    ->with(['children' => function ($q2) {
                        $q2->where('is_available', true)->orderBy('sort_order');
                    }])
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => MenuResource::collection($menus),
        ]);
    }

    /**
     * Lihat order saat ini (customer).
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}` yang didapatkan dari scan QR meja.
     */
    public function showOrder(Request $request): JsonResponse
    {
        $order = $request->attributes->get('customer_order');
        $order->load(['items', 'diningTable.organization']);

        return response()->json([
            'data' => new OrderDetailResource($order),
        ]);
    }

    /**
     * Lihat order tanpa token (public tracking).
     */
    public function showPublicOrder(Request $request, string $orderToken): JsonResponse
    {
        $order = Order::with(['items', 'diningTable.organization'])
            ->where('order_number', $orderToken)
            ->orWhere('public_token', $orderToken)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($this->checkAndExpireOrder($order)) {
            return response()->json([
                'status'         => 'expired',
                'payment_status' => 'expired',
                'message'        => 'Waktu pembayaran sudah habis. Silakan buat pesanan ulang.',
                'can_retry'      => true,
            ]);
        }

        return response()->json([
            'data' => new OrderDetailResource($order),
        ]);
    }

    /**
     * Tambah item ke order (customer).
     *
     * Payload menggunakan `selected_variants` untuk memilih variant.
     * Harga final (base_price + variant_total = unit_price) dikalkulasi
     * sepenuhnya di backend. Seluruh proses dibungkus DB transaction.
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
     *
     * @throws ValidationException
     */
    public function addItems(AddItemsRequest $request, OrderItemService $service): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        $service->addItems($order, $request->validated()['items']);

        return response()->json([
            'data'    => new OrderDetailResource(
                $order->fresh()->load('items', 'diningTable')
            ),
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    /**
     * Customer bayar QRIS.
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
     */
    public function payQris(Request $request, QrisService $qris): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order sudah dibayar.'], 422);
        }

        if ($order->allItems()->count() === 0) {
            return response()->json(['message' => 'Order belum memiliki item.'], 422);
        }

        $reference = "santap-{$order->id}";
        $result    = $qris->create($reference, (float) $order->total_amount);

        $order->update([
            'payment_status'    => PaymentStatus::Pending,
            'payment_method'    => 'qris',
            'payment_reference' => $reference,
        ]);

        return response()->json([
            'data' => [
                'qr_url'            => $result['qr_url'] ?? null,
                'payment_reference' => $reference,
            ],
            'message' => 'QRIS payment dibuat.',
        ]);
    }

    /**
     * Polling status QRIS.
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
     */
    public function qrisStatus(Request $request, QrisService $qris): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        if ($this->checkAndExpireOrder($order)) {
            return response()->json([
                'status'         => 'expired',
                'payment_status' => 'expired',
                'message'        => 'Waktu pembayaran sudah habis. Silakan buat pesanan ulang.',
                'can_retry'      => true,
            ]);
        }

        if (! $order->payment_reference) {
            return response()->json(['message' => 'Tidak ada QRIS payment aktif.'], 422);
        }

        $result = $qris->check($order->payment_reference);

        if (($result['status'] ?? '') === 'paid') {
            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_amount' => $order->total_amount,
                'change_amount'  => 0,
                'bill_status'    => BillStatus::Closed,
                'order_status'   => OrderStatus::Confirmed,
                'paid_at'        => now(),
                'closed_at'      => now(),
            ]);
        }

        return response()->json([
            'data' => [
                'payment_status' => $order->fresh()->payment_status,
            ],
        ]);
    }

    /**
     * Cancel QRIS (customer).
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
     */
    public function qrisCancel(Request $request, QrisService $qris): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        if (! $order->payment_reference) {
            return response()->json(['message' => 'Tidak ada QRIS payment aktif.'], 422);
        }

        $qris->cancel($order->payment_reference);

        $order->update([
            'payment_status'    => PaymentStatus::Cancelled,
            'payment_reference' => null,
        ]);

        return response()->json(['message' => 'QRIS payment dibatalkan.']);
    }

    /**
     * Helper to dynamically expire order if past payment_expires_at.
     */
    protected function checkAndExpireOrder(Order $order): bool
    {
        if ($order->payment_status === PaymentStatus::Pending && 
            $order->payment_expires_at && 
            $order->payment_expires_at->isPast()) {
            
            $order->update([
                'order_status'   => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Failed,
                'cancel_reason'  => 'Payment Timeout',
                'cancelled_at'   => now(),
            ]);

            if ($order->payment_reference) {
                try {
                    app(QrisService::class)->cancel($order->payment_reference);
                } catch (\Exception $e) {
                    // Ignore error silently
                }
            }
            
            return true;
        }

        return $order->order_status === OrderStatus::Cancelled && $order->cancel_reason === 'Payment Timeout';
    }
}
