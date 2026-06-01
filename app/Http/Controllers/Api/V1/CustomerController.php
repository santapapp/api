<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddItemsRequest;
use App\Http\Requests\Customer\CreateOrderRequest;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id'            => $org->id,
                'name'          => $org->name,
                'slug'          => $org->slug,
                'phone'         => $org->phone,
                'address'       => $org->address,
                'city'          => $org->city,
                'logo'          => $org->logo,
                'timezone'      => $org->timezone,
                'currency'      => $org->currency,
                'opening_hours' => $org->opening_hours,
                // Pajak & service charge — agar frontend bisa menampilkan rincian.
                'tax_enabled'            => $org->tax_enabled,
                'tax_rate'               => $org->tax_rate,
                'service_charge_enabled' => $org->service_charge_enabled,
                'service_charge_rate'    => $org->service_charge_rate,
            ],
        ]);
    }

    /**
     * Validasi / lookup meja dari QR token.
     *
     * Endpoint ini HANYA melakukan lookup meja + organisasi sebagai entry point.
     * Tidak membuat order, tidak membuat session, dan tidak menyimpan apa pun.
     * Token meja hanya dipakai frontend sebagai konteks sementara.
     */
    public function scanTable(string $qrToken): JsonResponse
    {
        $table = DiningTable::where('qr_token', $qrToken)
            ->where('is_active', true)
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
     * Checkout table order — buat order + QRIS dalam satu transaksi atomik.
     *
     * Order baru HANYA dibuat di sini (saat checkout). Sebelum ini tidak ada
     * order di database. Table order bersifat stateless: tidak ada session meja.
     *
     * State awal table order:
     * - order_type      = table_order
     * - bill_status     = none  (table order TIDAK memakai lifecycle bill open/closed)
     * - order_status    = pending
     * - payment_status  = pending
     * - payment_method  = qris
     * - payment_expires_at = now() + 5 menit
     *
     * Jika pembuatan QRIS gagal, seluruh transaksi di-rollback sehingga tidak
     * ada order/order_items yang menggantung.
     *
     * Endpoint publik — tidak butuh X-Public-Token.
     */
    public function createOrder(CreateOrderRequest $request, OrderItemService $service, QrisService $qris): JsonResponse
    {
        $table = DiningTable::where('qr_token', $request->validated('qr_token'))
            ->where('is_active', true)
            ->firstOrFail();

        $org             = Organization::findOrFail($table->organization_id);
        $taxSnapshot     = $org->tax_enabled ? (float) $org->tax_rate : 0.0;
        $serviceSnapshot = $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0;

        try {
            $result = DB::transaction(function () use ($table, $taxSnapshot, $serviceSnapshot, $request, $service, $qris) {
                $order = Order::create([
                    'order_number'                 => Order::generateOrderNumber($table->organization_id),
                    'public_token'                 => Str::random(32),
                    'organization_id'              => $table->organization_id,
                    'dining_table_id'              => $table->id,
                    'order_type'                   => OrderType::TableOrder,
                    'bill_status'                  => BillStatus::None,
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

                // Tambah item (juga menghitung ulang total).
                $service->addItems($order, $request->validated('items'));
                $order->refresh();

                // Buat QRIS — jika gagal, exception akan men-trigger rollback.
                $reference = "santap-{$order->id}";
                $qrisResult = $qris->create($reference, (float) $order->total_amount);

                $order->update(['payment_reference' => $reference]);

                return [
                    'order'      => $order,
                    'qris_result' => $qrisResult,
                    'reference'  => $reference,
                ];
            });
        } catch (ValidationException $e) {
            // Validasi item (mis. variant wajib) — teruskan sebagai 422.
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Customer createOrder gagal', [
                'table_id' => $table->id,
                'org_id'   => $table->organization_id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat pesanan. Silakan coba lagi.',
            ], 500);
        }

        /** @var Order $order */
        $order      = $result['order'];
        $qrisResult = $result['qris_result'];

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data'    => [
                'order_id'           => $order->id,
                'order_number'       => $order->order_number,
                'public_token'       => $order->public_token,
                'order_type'         => $order->order_type->value,
                'bill_status'        => $order->bill_status->value,
                'order_status'       => $order->order_status->value,
                'payment_status'     => $order->payment_status->value,
                'payment_method'     => $order->payment_method,
                'payment_reference'  => $order->payment_reference,
                'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
                'server_time'        => now()->toIso8601String(),
                'qris_data'          => [
                    'qr_url'            => $qrisResult['data']['actions'][0]['url'] ?? $qrisResult['data']['qr_url'] ?? null,
                    'qr_string'         => $qrisResult['data']['qr_string'] ?? null,
                    'payment_reference' => $result['reference'],
                ],
            ],
        ], 201);
    }

    /**
     * Detail order publik untuk tracking.
     *
     * Cari order berdasarkan `order_number` ATAU `public_token`. Tidak butuh
     * session, token meja, cart, maupun header X-Public-Token.
     *
     * Untuk table order yang masih pending, status expiry dievaluasi ulang agar
     * konsisten (order yang lewat deadline otomatis menjadi cancelled). Response
     * selalu memakai shape order detail yang sama — termasuk saat expired.
     */
    public function showPublicOrder(Request $request, string $order): JsonResponse
    {
        $model = $this->findPublicOrder($order);

        if (! $model) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        // Hanya table order yang punya mekanisme expiry otomatis di sini.
        if ($model->isTableOrder() && $model->isPaymentExpired()) {
            $model->markPaymentExpired();
        }

        $model->load(['items', 'diningTable.organization']);

        return response()->json([
            'data' => new OrderDetailResource($model),
        ]);
    }

    /**
     * Status pembayaran publik untuk table order (poll-able tanpa session).
     *
     * Frontend halaman /payments?order=<order_number> memakai endpoint ini untuk
     * me-refresh status. Cari order by `order_number`/`public_token`, lalu untuk
     * table order:
     * - Jika sudah lewat deadline → tandai cancelled (payment timeout).
     * - Jika masih pending → cek provider QRIS; jika paid → tandai paid + confirmed.
     *
     * Tidak butuh X-Public-Token, token meja, atau customer session.
     */
    public function paymentStatus(Request $request, string $order, QrisService $qris): JsonResponse
    {
        $model = $this->findPublicOrder($order);

        if (! $model) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        if ($model->isTableOrder()) {
            $this->refreshTableOrderPayment($model, $qris);
        }

        $model->refresh()->load(['items', 'diningTable.organization']);

        return response()->json([
            'data'        => new OrderDetailResource($model),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Lihat order saat ini (open bill — butuh X-Public-Token).
     *
     * Hanya dipakai untuk flow open bill aktif. Order disuplai oleh middleware
     * ensure.customer.token (order_type=open_bill, bill_status=open).
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
     * Tambah item ke open bill aktif (butuh X-Public-Token).
     *
     * Payload memakai `selected_variants`. Harga dikalkulasi backend dan
     * dibungkus DB transaction. Hanya untuk open bill (order_type=open_bill).
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
     * Buat pembayaran QRIS untuk open bill aktif (butuh X-Public-Token).
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
                'qr_url'            => $result['data']['actions'][0]['url'] ?? $result['data']['qr_url'] ?? null,
                'qr_string'         => $result['data']['qr_string'] ?? null,
                'payment_reference' => $reference,
            ],
            'message' => 'QRIS payment dibuat.',
        ]);
    }

    /**
     * Polling status QRIS untuk open bill aktif (butuh X-Public-Token).
     */
    public function qrisStatus(Request $request, QrisService $qris): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

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
     * Cancel QRIS untuk open bill aktif (butuh X-Public-Token).
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

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Cari order publik berdasarkan order_number atau public_token.
     */
    private function findPublicOrder(string $identifier): ?Order
    {
        return Order::where('order_number', $identifier)
            ->orWhere('public_token', $identifier)
            ->first();
    }

    /**
     * Refresh status pembayaran table order dari provider QRIS.
     *
     * - Expired → markPaymentExpired (cancelled, payment timeout).
     * - Pending + ada reference → cek provider; paid → tandai paid + confirmed.
     *
     * Table order tetap bill_status=none sepanjang lifecycle (bukan flow bill).
     * Kegagalan provider tidak men-throw — status DB terkini tetap dikembalikan.
     */
    private function refreshTableOrderPayment(Order $order, QrisService $qris): void
    {
        if ($order->isPaymentExpired()) {
            $order->markPaymentExpired();

            return;
        }

        if ($order->payment_status !== PaymentStatus::Pending || ! $order->payment_reference) {
            return;
        }

        try {
            $result = $qris->check($order->payment_reference);
        } catch (\Throwable $e) {
            Log::warning("Customer paymentStatus: gagal cek QRIS order {$order->id}: " . $e->getMessage());

            return;
        }

        if (($result['status'] ?? '') === 'paid') {
            $order->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_amount' => $order->total_amount,
                'change_amount'  => 0,
                'order_status'   => OrderStatus::Confirmed,
                'paid_at'        => now(),
            ]);
        }
    }
}
