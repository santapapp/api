<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AddItemsRequest;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\PayCashRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Services\OrderItemService;
use App\Services\OrganizationContext;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Order dikelola oleh staff (cashier/owner) per organisasi lewat header `X-Org-ID`.
 * Semua order tersimpan di tabel `orders`; itemnya di `order_items`.
 *
 * Open Bill menggunakan endpoint yang sama dengan cashier order, dibedakan
 * lewat `order_type=open_bill` (lihat `store`). Open bill adalah row `orders`
 * dengan `order_type=open_bill` dan `bill_status=open`.
 *
 * @tags Mobile Cashier Order
 */
class CashierOrderController extends Controller
{
    /**
     * List order aktif hari ini.
     *
     * Order `table_order` yang masih `payment_status=pending` (belum dibayar
     * dan berpotensi timeout) tidak ditampilkan agar dashboard tetap bersih.
     */
    public function index(): JsonResponse
    {
        $orgId  = app(OrganizationContext::class)->getOrganizationId();
        $orders = Order::where('organization_id', $orgId)
            ->whereDate('created_at', today())
            ->where(function ($query) {
                $query->where('order_type', '!=', OrderType::TableOrder)
                      ->orWhere('payment_status', '!=', PaymentStatus::Pending);
            })
            ->with(['diningTable', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Buat order baru (cashier order atau open bill).
     *
     * `order_type` valid: `cashier_order` atau `open_bill`.
     * Untuk `open_bill`, `bill_status` di-set `open` dan `public_token`
     * digenerate agar pelanggan bisa join. Order dibuat tanpa item —
     * tambahkan item lewat endpoint add items. Rate pajak & service
     * di-snapshot dari organisasi saat order dibuat. `created_by` diisi
     * dari user yang sedang login.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $org   = Organization::findOrFail($orgId);

        $isOpenBill = $request->order_type === 'open_bill';

        // Snapshot rate dikunci saat order dibuat
        $taxSnapshot     = $org->tax_enabled ? (float) $org->tax_rate : 0.0;
        $serviceSnapshot = $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0;

        $order = Order::create([
            'order_number'                 => Order::generateOrderNumber($orgId),
            'public_token'                 => $isOpenBill ? Str::random(32) : null,
            'organization_id'              => $orgId,
            'dining_table_id'              => $request->dining_table_id,
            'created_by'                   => $request->user()->id,
            'customer_name'                => $request->customer_name,
            'customer_phone'               => $request->customer_phone,
            'order_type'                   => $request->order_type,
            'bill_status'                  => $isOpenBill ? BillStatus::Open : BillStatus::None,
            'order_status'                 => OrderStatus::Pending,
            'payment_status'               => PaymentStatus::Unpaid,
            'tax_rate_snapshot'            => $taxSnapshot,
            'service_charge_rate_snapshot' => $serviceSnapshot,
            'subtotal_amount'              => 0,
            'discount_amount'              => 0,
            'tax_amount'                   => 0,
            'service_charge_amount'        => 0,
            'total_amount'                 => 0,
            'payment_amount'               => 0,
            'change_amount'                => 0,
            'note'                         => $request->note,
            'opened_at'                    => $isOpenBill ? now() : null,
        ]);

        return response()->json([
            'data'    => new OrderDetailResource($order->load('diningTable', 'createdBy')),
            'message' => 'Order berhasil dibuat.',
        ], 201);
    }

    /**
     * Detail order beserta item-itemnya.
     */
    public function show(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)
            ->with(['items', 'diningTable', 'createdBy', 'cancelledBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => new OrderDetailResource($order),
        ]);
    }

    /**
     * Tambah item ke order (cashier order atau open bill aktif).
     *
     * Setiap item: `menu_id` (harus produk), `quantity`, opsional `note`,
     * dan opsional `selected_variants` — daftar pilihan variant berbentuk
     * `{ variant_group_id, variant_id }`. Harga dikalkulasi backend dan
     * snapshot pilihan disimpan di `order_items.metadata.selected_options`.
     * Total order otomatis dihitung ulang.
     */
    public function addItems(AddItemsRequest $request, int $id, OrderItemService $service): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        $service->addItems($order, $request->validated('items'));

        return response()->json([
            'data'    => new OrderDetailResource($order->fresh()->load('items', 'diningTable', 'createdBy')),
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    /**
     * Hapus satu item dari order. Total order otomatis dihitung ulang.
     */
    public function removeItem(int $orderId, int $itemId): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($orderId);
        $item  = OrderItem::where('order_id', $order->id)->findOrFail($itemId);

        $item->delete();
        $order->recalculate();

        return response()->json(['message' => 'Item berhasil dihapus.']);
    }

    /**
     * Konfirmasi order sehingga masuk antrian dapur.
     *
     * Hanya untuk order dengan `order_status=pending`.
     */
    public function confirm(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->order_status !== OrderStatus::Pending) {
            return response()->json(['message' => 'Order tidak bisa dikonfirmasi.'], 422);
        }

        $order->update(['order_status' => OrderStatus::Confirmed]);

        return response()->json([
            'data'    => new OrderResource($order->fresh()->load('diningTable', 'createdBy')),
            'message' => 'Order dikonfirmasi.',
        ]);
    }

    /**
     * Bayar tunai (cash).
     *
     * Kirim `amount_received`. `change_amount` dihitung dari
     * `amount_received - total_amount`. Order menjadi `payment_status=paid`,
     * `bill_status=closed`, `order_status=completed`. Data pembayaran
     * tersimpan langsung di tabel `orders`.
     */
    public function payCash(PayCashRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order sudah dibayar.'], 422);
        }

        $amountReceived = (float) $request->amount_received;
        $totalAmount    = (float) $order->total_amount;
        $change         = round($amountReceived - $totalAmount, 2);

        if ($change < 0) {
            return response()->json(['message' => 'Jumlah uang kurang.'], 422);
        }

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'cash',
            'payment_amount' => $amountReceived,
            'change_amount'  => $change,
            'bill_status'    => BillStatus::Closed,
            'order_status'   => OrderStatus::Completed,
            'paid_at'        => now(),
            'closed_at'      => now(),
        ]);

        return response()->json([
            'data'    => new OrderResource($order->fresh()->load('diningTable')),
            'message' => 'Pembayaran tunai berhasil.',
        ]);
    }

    /**
     * Buat pembayaran QRIS.
     *
     * Mengembalikan `qr_url` dan `payment_reference` dari penyedia QRIS.
     * Order menjadi `payment_status=pending` hingga pembayaran terkonfirmasi.
     */
    public function payQris(int $id, QrisService $qris): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order sudah dibayar.'], 422);
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
     * Cek status pembayaran QRIS.
     *
     * Mengembalikan `payment_status` terkini dari tabel `orders`. Jika
     * penyedia melaporkan `paid`, order otomatis ditandai lunas dan ditutup.
     */
    public function qrisStatus(int $id, QrisService $qris): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

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
                'order_status'   => OrderStatus::Completed,
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
     * Batalkan pembayaran QRIS yang sedang aktif.
     */
    public function qrisCancel(int $id, QrisService $qris): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

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
     * Tutup bill (close).
     *
     * Dipakai untuk menutup open bill setelah pembayaran. `bill_status`
     * menjadi `closed`; `order_status` menjadi `completed` kecuali order
     * sudah `cancelled`.
     */
    public function close(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->bill_status === BillStatus::Closed) {
            return response()->json(['message' => 'Order sudah ditutup.'], 422);
        }

        $order->update([
            'bill_status'  => BillStatus::Closed,
            'order_status' => $order->order_status === OrderStatus::Cancelled
                ? OrderStatus::Cancelled
                : OrderStatus::Completed,
            'closed_at' => now(),
        ]);

        return response()->json([
            'data'    => new OrderResource($order->fresh()->load('diningTable')),
            'message' => 'Bill berhasil ditutup.',
        ]);
    }

    /**
     * Batalkan order.
     *
     * Tidak bisa membatalkan order yang sudah `completed`, `cancelled`,
     * atau sudah dibayar (`paid`). Jika ada QRIS pending, dibatalkan dulu
     * ke penyedia. Menyimpan `cancelled_by`, `cancel_reason`, `cancelled_at`.
     */
    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        // Guard: tidak bisa cancel jika sudah selesai
        if (in_array($order->order_status, [OrderStatus::Completed, OrderStatus::Cancelled])) {
            return response()->json(['message' => 'Order tidak dapat dibatalkan.'], 422);
        }

        // Guard: tidak bisa cancel jika sudah dibayar
        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order yang sudah dibayar tidak dapat dibatalkan.'], 422);
        }

        // Jika ada QRIS pending → cancel dulu ke Sekeco
        if ($order->payment_reference && $order->payment_status === PaymentStatus::Pending) {
            try {
                app(QrisService::class)->cancel($order->payment_reference);
            } catch (\Throwable) {
                // Log tapi jangan block cancel order
            }
        }

        $order->update([
            'order_status'    => OrderStatus::Cancelled,
            'payment_status'  => PaymentStatus::Cancelled,
            'payment_reference' => null,
            'cancelled_by'    => $request->user()->id,
            'cancel_reason'   => $request->cancel_reason,
            'cancelled_at'    => now(),
        ]);

        return response()->json([
            'data'    => new OrderResource($order->fresh()->load('diningTable', 'cancelledBy')),
            'message' => 'Order berhasil dibatalkan.',
        ]);
    }
}
