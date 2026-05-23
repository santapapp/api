<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Events\OrderItemStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KitchenOrderController extends Controller
{
    public function index(): JsonResponse
    {
        // Get active orders that need kitchen attention (pending, cooking)
        $orders = Order::with(['items' => function ($query) {
            $query->whereIn('status', [
                OrderItemStatus::Pending->value,
                OrderItemStatus::Cooking->value,
                OrderItemStatus::Ready->value,
            ]);
        }, 'diningTable'])
        ->whereIn('status', [
            OrderStatus::Pending->value,
            OrderStatus::Accepted->value,
            OrderStatus::Cooking->value,
            OrderStatus::Ready->value,
        ])
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function updateItemStatus(Request $request, int $id): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $orderItem = OrderItem::where('id', $id)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$orderItem) {
            return response()->json(['message' => 'Order item tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,cooking,ready,served,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        $orderItem->update([
            'status' => $request->status,
        ]);

        // Broadcast event
        broadcast(new OrderItemStatusUpdated($orderItem));

        // Auto-update parent order status if all items are ready/served
        $order = $orderItem->order;
        $allItems = $order->items;
        
        $allServedOrCancelled = $allItems->every(fn($item) => in_array($item->status->value, ['served', 'cancelled']));
        $allReadyServedOrCancelled = $allItems->every(fn($item) => in_array($item->status->value, ['ready', 'served', 'cancelled']));
        
        if ($allServedOrCancelled) {
            $order->update(['status' => OrderStatus::Served]);
        } elseif ($allReadyServedOrCancelled) {
            $order->update(['status' => OrderStatus::Ready]);
        } elseif ($request->status === 'cooking' && $order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Cooking]);
        }

        return response()->json([
            'message' => 'Status order item berhasil diperbarui.',
            'data' => $orderItem,
        ]);
    }
}
