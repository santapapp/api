<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Events\OpenBillRepeatOrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddItemsRequest;
use App\Http\Requests\Customer\CreateOrderRequest;
use App\Http\Resources\MenuResource;
use App\Http\Resources\OrderDetailResource;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Organization;
use App\Services\MediaService;
use App\Services\OrderItemService;
use App\Services\OrderQrisPaymentService;
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
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'phone' => $org->phone,
                'address' => $org->address,
                'city' => $org->city,
                'logo' => MediaService::toUrl($org->logo),
                'timezone' => $org->timezone,
                'currency' => $org->currency,
                'opening_hours' => $org->opening_hours,
                // Pajak & service charge — agar frontend bisa menampilkan rincian.
                'tax_enabled' => $org->tax_enabled,
                'tax_rate' => $org->tax_rate !== null ? (float) $org->tax_rate : null,
                'service_charge_enabled' => $org->service_charge_enabled,
                'service_charge_rate' => $org->service_charge_rate !== null ? (float) $org->service_charge_rate : null,
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
                    'id' => $table->organization->id,
                    'name' => $table->organization->name,
                    'slug' => $table->organization->slug,
                ],
                'table' => [
                    'id' => $table->id,
                    'name' => $table->name,
                    'code' => $table->code,
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
     * - payment_expires_at = now() + QRIS_EXPIRY_MINUTES (default 15 menit)
     *
     * Response menyertakan `payment_expires_at` dan `server_time` (ISO-8601) — pakai
     * keduanya untuk menghitung countdown yang akurat di klien (offset waktu server).
     * Pantau status lewat GET /v1/customer/orders/{order}/payment-status.
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

        $org = Organization::findOrFail($table->organization_id);
        $taxSnapshot = $org->tax_enabled ? (float) $org->tax_rate : 0.0;
        $serviceSnapshot = $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0;

        try {
            $result = DB::transaction(function () use ($table, $taxSnapshot, $serviceSnapshot, $request, $service, $qris) {
                $order = Order::create([
                    'order_number' => Order::generateOrderNumber($table->organization_id),
                    'public_token' => Str::random(32),
                    'organization_id' => $table->organization_id,
                    'dining_table_id' => $table->id,
                    'order_type' => OrderType::TableOrder,
                    'bill_status' => BillStatus::None,
                    'order_status' => OrderStatus::Pending,
                    'payment_status' => PaymentStatus::Pending,
                    'payment_method' => 'qris',
                    'tax_rate_snapshot' => $taxSnapshot,
                    'service_charge_rate_snapshot' => $serviceSnapshot,
                    'subtotal_amount' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'service_charge_amount' => 0,
                    'total_amount' => 0,
                    'payment_amount' => 0,
                    'change_amount' => 0,
                    'opened_at' => now(),
                    'payment_expires_at' => now()->addMinutes((int) config('santap.qris.expiry_minutes', 15)),
                ]);

                // Tambah item (juga menghitung ulang total).
                $service->addItems($order, $request->validated('items'));
                $order->refresh();

                // Buat QRIS — jika gagal, exception akan men-trigger rollback.
                $reference = "santap-{$order->id}";
                $qrisResult = $qris->create($reference, (float) $order->total_amount);

                $order->update(['payment_reference' => $reference]);

                return [
                    'order' => $order,
                    'qris_result' => $qrisResult,
                    'reference' => $reference,
                ];
            });
        } catch (ValidationException $e) {
            // Validasi item (mis. variant wajib) — teruskan sebagai 422.
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Customer createOrder gagal', [
                'table_id' => $table->id,
                'org_id' => $table->organization_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat pesanan. Silakan coba lagi.',
            ], 500);
        }

        /** @var Order $order */
        $order = $result['order'];
        $qrisResult = $result['qris_result'];

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'public_token' => $order->public_token,
                'order_type' => $order->order_type->value,
                'bill_status' => $order->bill_status->value,
                'order_status' => $order->order_status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method,
                'payment_reference' => $order->payment_reference,
                'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
                'server_time' => now()->toIso8601String(),
                'qris_data' => [
                    'qr_url' => $qrisResult['data']['actions'][0]['url'] ?? $qrisResult['data']['qr_url'] ?? null,
                    'qr_string' => $qrisResult['data']['qr_string'] ?? null,
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
     * Untuk table order, status pembayaran disinkronkan ke provider QRIS lebih
     * dulu (gateway = source of truth) sebelum keputusan expiry, sehingga endpoint
     * ini idempotent dan aman untuk polling progres pesanan (pending → confirmed →
     * preparing → ready → completed). Response selalu memakai shape order detail
     * yang sama — termasuk saat cancelled/expired.
     */
    public function showPublicOrder(Request $request, string $order, QrisService $qris): JsonResponse
    {
        $model = $this->findPublicOrder($order);

        if (! $model) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        // Table order: sinkronkan ke provider lebih dulu (gateway = source of truth)
        // sebelum keputusan expiry — sama seperti endpoint payment-status.
        if ($model->isTableOrder()) {
            $this->refreshTableOrderPayment($model, $qris);
            $model->refresh();
        }

        $model->load(['items', 'diningTable.organization']);

        return response()->json([
            'data' => new OrderDetailResource($model),
        ]);
    }

    /**
     * Status pembayaran publik untuk table order (poll-able tanpa session).
     *
     * Endpoint utama untuk polling dari halaman pembayaran. Cari order by
     * `order_number`/`public_token`, lalu untuk table order jalankan sinkronisasi
     * ke provider QRIS dengan urutan (gateway = source of truth):
     * 1. SELALU cek provider lebih dulu — jika settlement → tandai paid + confirmed
     *    (termasuk rekonsiliasi order yang terlanjur cancelled).
     * 2. Provider menyatakan expired/cancelled/denied → tandai cancelled.
     * 3. Provider masih pending DAN sudah lewat deadline → baru tandai timeout.
     *
     * Idempotent: aman dipanggil berulang. Response menyertakan `server_time`
     * (ISO-8601) untuk sinkronisasi countdown klien. Tidak butuh X-Public-Token,
     * token meja, atau customer session.
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
            'data' => new OrderDetailResource($model),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Lihat order saat ini (open bill — butuh X-Public-Token).
     *
     * Hanya dipakai untuk flow open bill aktif. Order disuplai oleh middleware
     * ensure.customer.token (order_type=open_bill, bill_status=open).
     *
     * Load 'organization' secara langsung untuk menangani kasus open bill
     * tanpa meja (dining_table_id = null).
     */
    public function showOrder(Request $request): JsonResponse
    {
        $order = $request->attributes->get('customer_order');
        $order->load(['items', 'diningTable.organization', 'organization']);

        return response()->json([
            'data' => new OrderDetailResource($order),
        ]);
    }

    /**
     * Tambah item ke open bill aktif (butuh X-Public-Token).
     *
     * Payload kanonik memakai `selected_options[{group_id, option_id}]`.
     * `selected_variants` tetap diterima untuk backward compatibility. Harga
     * dikalkulasi backend dan dibungkus DB transaction. Hanya untuk open bill
     * (order_type=open_bill). Pending/paid QRIS memblokir tambah item.
     *
     * @throws ValidationException
     */
    public function addItems(
        AddItemsRequest $request,
        OrderItemService $service,
        OrderQrisPaymentService $payments,
        QrisService $qris,
    ): JsonResponse {
        $order = $request->attributes->get('customer_order');
        $order = $payments->ensureItemsMutable($order, $qris);

        $batch = $service->addItems($order, $request->validated()['items']);
        $freshOrder = $order->fresh()->load('items', 'diningTable', 'organization');
        event(OpenBillRepeatOrderCreated::fromOrder($freshOrder, $batch));

        return response()->json([
            'data' => new OrderDetailResource($freshOrder),
            'batch' => $batch,
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    /**
     * Buat pembayaran QRIS untuk open bill aktif (butuh X-Public-Token).
     *
     * Mengembalikan `qr_url`/`qr_string`, `payment_reference`, serta
     * `payment_expires_at` dan `server_time` (ISO-8601) untuk countdown klien.
     * Order menjadi `payment_status=pending` dengan deadline
     * now() + QRIS_EXPIRY_MINUTES (default 15 menit). Pantau lewat
     * GET /v1/customer/order/qris-status.
     */
    public function payQris(Request $request, QrisService $qris, OrderQrisPaymentService $payments): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        $result = $payments->create($order, $qris);
        /** @var Order $freshOrder */
        $freshOrder = $result['order'];

        return response()->json([
            'data' => $payments->responsePayload($freshOrder, $result['qris']),
            'message' => $result['reused']
                ? 'Order ini masih memiliki QRIS pending aktif.'
                : 'QRIS payment dibuat.',
        ]);
    }

    /**
     * Polling status QRIS untuk open bill aktif (butuh X-Public-Token).
     *
     * Mengecek transaksi ke provider Sekeco/Midtrans (gateway = source of truth);
     * jika settlement → order ditandai lunas dan bill ditutup. Mengembalikan
     * `payment_status` terkini. Idempotent — aman dipanggil berulang.
     */
    public function qrisStatus(Request $request, QrisService $qris, OrderQrisPaymentService $payments): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        $before = $order->payment_status->value;
        $sync = $payments->sync($order, $qris);
        $freshOrder = $sync['order'];
        $result = $sync['result'];

        Log::info('QRIS sync (open bill)', [
            'order_no' => $order->order_number,
            'payment_reference' => $order->payment_reference,
            'payment_status_before' => $before,
            'provider_status' => $result['status'],
            'provider_tx_status' => $result['transaction_status'],
        ]);

        return response()->json([
            'data' => $payments->responsePayload($freshOrder, $sync['qris']),
        ]);
    }

    /**
     * Cancel QRIS untuk open bill aktif (butuh X-Public-Token).
     */
    public function qrisCancel(Request $request, QrisService $qris, OrderQrisPaymentService $payments): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        $payments->cancel($order, $qris);

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
     * URUTAN PENTING (gateway = source of truth):
     * 1. Sudah paid → idempotent, tidak ada aksi.
     * 2. Ada reference → SELALU cek provider lebih dulu (bahkan jika sudah lewat
     *    deadline) → jika settlement, tandai paid. Termasuk rekonsiliasi order
     *    yang terlanjur cancelled bila provider ternyata lunas.
     * 3. Provider eksplisit expired/cancelled/denied → cancel dengan alasan.
     * 4. Provider masih pending DAN sudah lewat deadline lokal → baru expire.
     *
     * Timeout lokal TIDAK PERNAH berjalan sebelum sync. Kegagalan provider tidak
     * men-throw — status DB terkini tetap dikembalikan.
     */
    private function refreshTableOrderPayment(Order $order, QrisService $qris): void
    {
        // (1) Idempotent — sudah lunas.
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        // Tanpa reference tak ada yang bisa disinkronkan; expire hanya bila pending & lewat deadline.
        if (! $order->payment_reference) {
            if ($order->isPaymentExpired()) {
                Log::info('QRIS sync (table order): expire tanpa payment_reference', [
                    'order_no' => $order->order_number,
                    'payment_status_before' => $order->payment_status->value,
                ]);
                $order->markPaymentExpired('Payment Timeout');
            }

            return;
        }

        // (2) Source of truth: cek provider lebih dulu.
        $before = $order->payment_status->value;
        $result = $qris->check($order->payment_reference);

        Log::info('QRIS sync (table order)', [
            'order_no' => $order->order_number,
            'payment_reference' => $order->payment_reference,
            'payment_status_before' => $before,
            'provider_status' => $result['status'],
            'provider_tx_status' => $result['transaction_status'],
        ]);

        if ($result['paid']) {
            $wasCancelled = $order->payment_status === PaymentStatus::Cancelled;
            $order->markPaid();

            Log::info($wasCancelled
                ? 'QRIS sync (table order): order DIREKONSILIASI cancelled→PAID'
                : 'QRIS sync (table order): order ditandai PAID', [
                    'order_no' => $order->order_number,
                    'payment_status_after' => $order->payment_status->value,
                ]);

            return;
        }

        // Order sudah cancelled & provider belum lunas → biarkan (tidak ada perubahan).
        if ($order->payment_status === PaymentStatus::Cancelled) {
            return;
        }

        // (3) Provider eksplisit menyatakan transaksi gagal.
        if (in_array($result['status'], ['expired', 'cancelled', 'denied'], true)) {
            $order->markPaymentExpired('Payment '.$result['status']);

            Log::info('QRIS sync (table order): order di-cancel oleh provider', [
                'order_no' => $order->order_number,
                'reason' => $result['status'],
            ]);

            return;
        }

        // (4) Provider masih pending → expire hanya jika sudah lewat deadline lokal.
        if ($order->isPaymentExpired()) {
            $order->markPaymentExpired('Payment Timeout');

            Log::info('QRIS sync (table order): order expired (timeout lokal, provider masih pending)', [
                'order_no' => $order->order_number,
            ]);
        }
    }
}
