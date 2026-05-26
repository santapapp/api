<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\ItemType;
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
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Services\OrganizationContext;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CashierOrderController extends Controller
{
    /**
     * List order aktif hari ini.
     */
    public function index(): JsonResponse
    {
        $orgId  = app(OrganizationContext::class)->getOrganizationId();
        $orders = Order::where('organization_id', $orgId)
            ->whereDate('created_at', today())
            ->with(['diningTable', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Buat order baru — snapshot tax/service dari organization saat create.
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
     * Detail order lengkap dengan items.
     */
    public function show(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)
            ->with(['items.children', 'diningTable', 'createdBy', 'cancelledBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => new OrderDetailResource($order),
        ]);
    }

    /**
     * Tambah item ke order — snapshot item_type dari menu.type.
     */
    public function addItems(AddItemsRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        foreach ($request->items as $itemData) {
            $menu = Menu::where('organization_id', $orgId)->findOrFail($itemData['menu_id']);

            // Snapshot item_type dari menu.type (product/variant/addon)
            $itemType = match ($menu->type->value) {
                'variant' => ItemType::Variant->value,
                'addon'   => ItemType::Addon->value,
                default   => ItemType::Product->value,
            };

            $qty      = $itemData['quantity'];
            $subtotal = round((float) $menu->price * $qty, 2);

            $item = OrderItem::create([
                'order_id'   => $order->id,
                'menu_id'    => $menu->id,
                'item_type'  => $itemType,
                'name'       => $menu->name,
                'price'      => $menu->price,
                'quantity'   => $qty,
                'subtotal'   => $subtotal,
                'note'       => $itemData['note'] ?? null,
            ]);

            // Children (variant/addon)
            if (! empty($itemData['children'])) {
                foreach ($itemData['children'] as $childData) {
                    $childMenu = Menu::where('organization_id', $orgId)->findOrFail($childData['menu_id']);

                    $childItemType = match ($childMenu->type->value) {
                        'variant' => ItemType::Variant->value,
                        'addon'   => ItemType::Addon->value,
                        default   => ItemType::Product->value,
                    };

                    $childSubtotal = round((float) $childMenu->price * $qty, 2);

                    OrderItem::create([
                        'order_id'       => $order->id,
                        'menu_id'        => $childMenu->id,
                        'parent_item_id' => $item->id,
                        'item_type'      => $childItemType,
                        'name'           => $childMenu->name,
                        'price'          => $childMenu->price,
                        'quantity'       => $qty,
                        'subtotal'       => $childSubtotal,
                    ]);
                }
            }
        }

        $order->recalculate();

        return response()->json([
            'data'    => new OrderDetailResource($order->fresh()->load('items.children', 'diningTable', 'createdBy')),
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    /**
     * Hapus item dari order (cascade children via FK).
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
     * Konfirmasi order → kirim ke kitchen.
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
     * Bayar cash — hitung change_amount.
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
     * Buat QRIS payment.
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
                'qr_url'            => $result['qr_url'] ?? null,
                'payment_reference' => $reference,
            ],
            'message' => 'QRIS payment dibuat.',
        ]);
    }

    /**
     * Cek status QRIS dan update order jika sudah paid.
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
     * Cancel QRIS payment aktif.
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
     * Close bill.
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
     * Cancel order — simpan cancelled_by, cancel_reason, cancelled_at.
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
