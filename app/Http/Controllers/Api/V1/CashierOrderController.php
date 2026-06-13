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
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Services\OrderItemService;
use App\Services\OrderLifecycleService;
use App\Services\OrderQrisPaymentService;
use App\Services\OrganizationContext;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Order dikelola oleh staff (cashier/owner) per organisasi lewat header `X-Org-ID`.
 *
 * @tags Mobile Cashier Order
 */
class CashierOrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $orders = Order::where('organization_id', $orgId)
            ->whereDate('created_at', today())
            ->when(request('order_type'), fn ($query, string $type) => $query->where('order_type', $type))
            ->when(request('bill_status'), fn ($query, string $status) => $query->where('bill_status', $status))
            ->where(function ($query): void {
                $query->where('order_type', '!=', OrderType::TableOrder)
                    ->orWhere('payment_status', '!=', PaymentStatus::Pending);
            })
            ->with(['items', 'diningTable', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $org = Organization::findOrFail($orgId);
        $isOpenBill = $request->order_type === OrderType::OpenBill->value;
        $tableId = $request->dining_table_id !== null ? (int) $request->dining_table_id : null;

        if ($tableId !== null) {
            DiningTable::query()
                ->where('organization_id', $orgId)
                ->where('is_active', true)
                ->findOrFail($tableId);
        }

        if (
            $isOpenBill
            && $tableId !== null
            && Order::query()
                ->where('organization_id', $orgId)
                ->where('dining_table_id', $tableId)
                ->where('order_type', OrderType::OpenBill)
                ->where('bill_status', BillStatus::Open)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'dining_table_id' => 'Meja ini sudah memiliki Open Bill aktif.',
            ]);
        }

        $order = Order::create([
            'order_number' => Order::generateOrderNumber($orgId),
            'public_token' => $isOpenBill ? Str::random(32) : null,
            'organization_id' => $orgId,
            'dining_table_id' => $tableId,
            'order_marker_number' => $org->order_marker_mode !== 'disabled'
                ? $request->order_marker_number
                : null,
            'created_by' => $request->user()->id,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'order_type' => $request->order_type,
            'bill_status' => $isOpenBill ? BillStatus::Open : BillStatus::None,
            'order_status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'tax_rate_snapshot' => $org->tax_enabled ? (float) $org->tax_rate : 0.0,
            'service_charge_rate_snapshot' => $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'total_amount' => 0,
            'payment_amount' => 0,
            'change_amount' => 0,
            'note' => $request->note,
            'opened_at' => $isOpenBill ? now() : null,
        ]);

        return response()->json([
            'data' => new OrderDetailResource($order->load('diningTable', 'createdBy')),
            'message' => 'Order berhasil dibuat.',
        ], 201);
    }

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

    public function addItems(
        AddItemsRequest $request,
        int $id,
        OrderItemService $items,
        OrderQrisPaymentService $payments,
        QrisService $qris,
    ): JsonResponse {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);
        $order = $payments->ensureItemsMutable($order, $qris);

        $batch = $items->addItems($order, $request->validated('items'));

        return response()->json([
            'data' => new OrderDetailResource($order->fresh()->load('items', 'diningTable', 'createdBy')),
            'batch' => $batch,
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    public function updateItem(
        Request $request,
        int $orderId,
        int $itemId,
        OrderItemService $items,
        OrderQrisPaymentService $payments,
        QrisService $qris,
    ): JsonResponse {
        $this->assertSalesRole();

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($orderId);
        $order = $payments->ensureItemsMutable($order, $qris);
        $item = OrderItem::where('order_id', $order->id)->findOrFail($itemId);

        $items->updateQuantity($order, $item, (int) $data['quantity']);

        return response()->json([
            'data' => new OrderDetailResource($order->fresh()->load('items', 'diningTable', 'createdBy')),
            'message' => 'Quantity item berhasil diperbarui.',
        ]);
    }

    public function removeItem(
        int $orderId,
        int $itemId,
        OrderItemService $items,
        OrderQrisPaymentService $payments,
        QrisService $qris,
    ): JsonResponse {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($orderId);
        $order = $payments->ensureItemsMutable($order, $qris);
        $item = OrderItem::where('order_id', $order->id)->findOrFail($itemId);

        $items->removeItem($order, $item);

        return response()->json(['message' => 'Item berhasil dihapus.']);
    }

    public function confirm(int $id): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->order_status !== OrderStatus::Pending) {
            return response()->json(['message' => 'Order tidak bisa dikonfirmasi.'], 422);
        }

        $order->update(['order_status' => OrderStatus::Confirmed]);

        return response()->json([
            'data' => new OrderResource($order->fresh()->load('diningTable', 'createdBy')),
            'message' => 'Order dikonfirmasi.',
        ]);
    }

    public function payCash(PayCashRequest $request, int $id): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order sudah dibayar.'], 422);
        }

        if ($order->order_status === OrderStatus::Cancelled || $order->cancelled_at !== null) {
            return response()->json(['message' => 'Order yang sudah dibatal tidak dapat dibayar.'], 422);
        }

        $amountReceived = (float) $request->amount_received;
        $totalAmount = (float) $order->total_amount;
        $change = round($amountReceived - $totalAmount, 2);

        if ($change < 0) {
            return response()->json(['message' => 'Jumlah uang kurang.'], 422);
        }

        $nextOrderStatus = in_array($order->order_status, [OrderStatus::Pending, OrderStatus::Cancelled], true)
            ? OrderStatus::Confirmed
            : $order->order_status;

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => 'cash',
            'payment_amount' => $amountReceived,
            'change_amount' => $change,
            'bill_status' => BillStatus::Closed,
            'order_status' => $nextOrderStatus,
            'paid_at' => now(),
            'closed_at' => now(),
        ]);

        return response()->json([
            'data' => new OrderResource($order->fresh()->load('diningTable')),
            'message' => 'Pembayaran tunai berhasil.',
        ]);
    }

    public function payQris(int $id, QrisService $qris, OrderQrisPaymentService $payments): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        $result = $payments->create($order, $qris);
        $freshOrder = $result['order'];

        return response()->json([
            'data' => $payments->responsePayload($freshOrder, $result['qris']),
            'message' => $result['reused']
                ? 'Order ini masih memiliki QRIS pending aktif.'
                : 'QRIS payment dibuat.',
        ]);
    }

    public function qrisStatus(int $id, QrisService $qris, OrderQrisPaymentService $payments): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        $before = $order->payment_status->value;
        $sync = $payments->sync($order, $qris);
        $freshOrder = $sync['order'];
        $result = $sync['result'];

        Log::info('QRIS sync (cashier)', [
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

    public function qrisCancel(int $id, QrisService $qris, OrderQrisPaymentService $payments): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        $payments->cancel($order, $qris);

        return response()->json(['message' => 'QRIS payment dibatalkan.']);
    }

    public function close(int $id): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if ($order->bill_status === BillStatus::Closed) {
            return response()->json(['message' => 'Order sudah ditutup.'], 422);
        }

        if ($order->order_status === OrderStatus::Cancelled || $order->cancelled_at !== null) {
            return response()->json(['message' => 'Order yang sudah dibatal tidak dapat ditutup.'], 422);
        }

        $order->update([
            'bill_status' => BillStatus::Closed,
            'order_status' => $order->order_status === OrderStatus::Cancelled
                ? OrderStatus::Cancelled
                : $order->order_status,
            'closed_at' => now(),
        ]);

        return response()->json([
            'data' => new OrderResource($order->fresh()->load('diningTable')),
            'message' => 'Bill berhasil ditutup.',
        ]);
    }

    public function cancel(CancelOrderRequest $request, int $id, OrderLifecycleService $lifecycle): JsonResponse
    {
        $this->assertSalesRole();

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $order = Order::where('organization_id', $orgId)->findOrFail($id);

        if (in_array($order->order_status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
            return response()->json(['message' => 'Order tidak dapat dibatalkan.'], 422);
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json(['message' => 'Order yang sudah dibayar tidak dapat dibatalkan.'], 422);
        }

        $lifecycle->cancelOrder($order, $request->user(), $request->cancel_reason);

        return response()->json([
            'data' => new OrderResource($order->fresh()->load('diningTable', 'cancelledBy')),
            'message' => 'Order berhasil dibatalkan.',
        ]);
    }

    private function assertSalesRole(): void
    {
        $role = app(OrganizationContext::class)->getRole();

        if (! in_array($role, ['owner', 'cashier'], true)) {
            throw new AccessDeniedHttpException('Hanya owner atau cashier yang boleh mengubah order/payment.');
        }
    }
}
