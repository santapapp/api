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

/**
 * Antrian dapur per organisasi (header `X-Org-ID`).
 *
 * @tags Mobile Kitchen
 */
class KitchenOrderController extends Controller
{
    /**
     * Antrian order untuk dapur.
     *
     * Mengembalikan order dengan `order_status` `confirmed` atau `preparing`,
     * beserta item-itemnya. Cocok untuk polling layar dapur.
     */
    public function index(): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $orders = Order::where('organization_id', $orgId)
            ->whereIn('order_status', [
                OrderStatus::Confirmed,
                OrderStatus::Preparing,
            ])
            ->with(['items', 'diningTable'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Update status satu item order dari dapur.
     *
     * Nilai `item_status` yang valid: `preparing`, `ready`, `served`, `cancelled`.
     * Jika semua item sudah `served`, `order_status` otomatis menjadi `ready`;
     * jika sebelumnya `confirmed`, otomatis menjadi `preparing`.
     */
    public function updateItemStatus(UpdateItemStatusRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $item = OrderItem::whereHas('order', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->findOrFail($id);

        $item->update(['item_status' => $request->item_status]);

        // Cek apakah semua items sudah served → update order_status ke ready
        $order     = $item->order;
        $allServed = $order->allItems()
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
