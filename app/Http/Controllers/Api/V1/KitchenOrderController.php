<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\UpdateItemStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;

class KitchenOrderController extends Controller
{
    /**
     * List order masuk untuk kitchen (polling).
     */
    public function index(): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $orders = Order::where('organization_id', $orgId)
            ->whereIn('order_status', [
                OrderStatus::Confirmed,
                OrderStatus::Preparing,
            ])
            ->with(['items.children', 'diningTable'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Update status item dari kitchen.
     */
    public function updateItemStatus(UpdateItemStatusRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $item = OrderItem::whereHas('order', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->findOrFail($id);

        $item->update(['item_status' => $request->item_status]);

        // Cek apakah semua root items sudah served → update order_status ke ready
        $order     = $item->order;
        $allServed = $order->allItems()
            ->whereNull('parent_item_id')
            ->where('item_status', '!=', ItemStatus::Served->value)
            ->where('item_status', '!=', ItemStatus::Cancelled->value)
            ->doesntExist();

        if ($allServed) {
            $order->update(['order_status' => OrderStatus::Ready]);
        } elseif ($order->order_status === OrderStatus::Confirmed) {
            $order->update(['order_status' => OrderStatus::Preparing]);
        }

        return response()->json([
            'data'    => $item->fresh(),
            'message' => 'Status item diupdate.',
        ]);
    }
}
