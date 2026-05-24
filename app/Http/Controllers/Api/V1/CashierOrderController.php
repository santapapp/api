<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
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
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $orders = Order::where('organization_id', $orgId)
            ->whereDate('created_at', today())
            ->with(['items.children', 'diningTable'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Buat order baru (cashier_order atau open_bill).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_type' => 'required|string|in:cashier_order,open_bill',
            'dining_table_id' => 'nullable|integer|exists:dining_tables,id',
            'note' => 'nullable|string|max:500',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $isOpenBill = $request->order_type === 'open_bill';

        $order = Order::create([
            'order_number' => Order::generateOrderNumber($orgId),
            'public_token' => $isOpenBill ? Str::random(32) : null,
            'organization_id' => $orgId,
            'dining_table_id' => $request->dining_table_id,
            'created_by' => $request->user()->id,
            'order_type' => $request->order_type,
            'bill_status' => $isOpenBill ? BillStatus::Open : BillStatus::None,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'note' => $request->note,
            'opened_at' => $isOpenBill ? now() : null,
        ]);

        return response()->json([
            'data' => $order->load('diningTable'),
            'message' => 'Order berhasil dibuat.',
        ], 201);
    }

    /**
     * Detail order.
     */
    public function show(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $order = Order::where('organization_id', $orgId)
            ->with(['items.children', 'diningTable', 'createdBy'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    /**
     * Tambah item ke order.
     */
    public function addItems(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:500',
            'items.*.children' => 'nullable|array',
            'items.*.children.*.menu_id' => 'required|integer|exists:menus,id',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        foreach ($request->items as $itemData) {
            $menu = Menu::where('organization_id', $orgId)->findOrFail($itemData['menu_id']);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'menu_id' => $menu->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'quantity' => $itemData['quantity'],
                'note' => $itemData['note'] ?? null,
            ]);

            // Children (variant/addon)
            if (! empty($itemData['children'])) {
                foreach ($itemData['children'] as $childData) {
                    $childMenu = Menu::where('organization_id', $orgId)->findOrFail($childData['menu_id']);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_id' => $childMenu->id,
                        'parent_item_id' => $item->id,
                        'name' => $childMenu->name,
                        'price' => $childMenu->price,
                        'quantity' => $itemData['quantity'], // sama dengan parent
                    ]);
                }
            }
        }

        $order->recalculate();

        return response()->json([
            'data' => $order->fresh()->load('items.children'),
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    /**
     * Hapus item dari order.
     */
    public function removeItem(int $orderId, int $itemId): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($orderId);
        $item = OrderItem::where('order_id', $order->id)->findOrFail($itemId);

        $item->delete(); // cascade children via FK
        $order->recalculate();

        return response()->json(['message' => 'Item berhasil dihapus.']);
    }

    /**
     * Konfirmasi order (kirim ke kitchen).
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
            'data' => $order->fresh(),
            'message' => 'Order dikonfirmasi.',
        ]);
    }

    /**
     * Bayar cash.
     */
    public function payCash(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount_received' => 'required|numeric|min:0',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order sudah dibayar.'], 422);
        }

        $change = $request->amount_received - $order->total_amount;

        if ($change < 0) {
            return response()->json(['message' => 'Jumlah uang kurang.'], 422);
        }

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'cash',
            'payment_amount' => $order->total_amount,
            'bill_status' => BillStatus::Closed,
            'order_status' => OrderStatus::Completed,
            'paid_at' => now(),
            'closed_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'order' => $order->fresh(),
                'change' => $change,
            ],
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

        $result = $qris->create($reference, (float) $order->total_amount);

        $order->update([
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => 'qris',
            'payment_reference' => $reference,
        ]);

        return response()->json([
            'data' => [
                'qr_url' => $result['qr_url'] ?? null,
                'payment_reference' => $reference,
            ],
            'message' => 'QRIS payment dibuat.',
        ]);
    }

    /**
     * Cek status QRIS.
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
                'bill_status' => BillStatus::Closed,
                'order_status' => OrderStatus::Completed,
                'paid_at' => now(),
                'closed_at' => now(),
            ]);
        }

        return response()->json([
            'data' => [
                'payment_status' => $order->fresh()->payment_status,
            ],
        ]);
    }

    /**
     * Cancel QRIS.
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
            'payment_status' => PaymentStatus::Cancelled,
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
            'bill_status' => BillStatus::Closed,
            'order_status' => $order->order_status === OrderStatus::Cancelled
                ? OrderStatus::Cancelled
                : OrderStatus::Completed,
            'closed_at' => now(),
        ]);

        return response()->json([
            'data' => $order->fresh(),
            'message' => 'Bill berhasil ditutup.',
        ]);
    }
}
