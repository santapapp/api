<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
     *
     * item_status adalah SUMBER KEBENARAN — setelah diubah, `order_status`
     * diturunkan ulang dari agregat seluruh item (item-driven):
     *   semua item served → completed · semua ready → ready ·
     *   ada yang diproses → preparing · sisanya → confirmed.
     */
    public function updateItemStatus(UpdateItemStatusRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $item = OrderItem::whereHas('order', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->findOrFail($id);

        $item->update(['item_status' => $request->item_status]);

        // Rollup: turunkan order_status dari agregat item.
        $item->order->syncStatusFromItems();

        // Dispatch broadcast event for item status updates
        event(\App\Events\OrderItemStatusUpdated::fromItem($item->fresh()));

        return response()->json([
            'data'    => $item->fresh(),
            'message' => 'Status item diupdate.',
        ]);
    }
}
