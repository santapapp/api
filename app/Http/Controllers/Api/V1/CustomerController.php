<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\ItemType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddItemsRequest;
use App\Http\Resources\MenuResource;
use App\Http\Resources\OrderDetailResource;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Services\QrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Data publik organisasi berdasarkan slug.
     */
    public function organization(string $slug): JsonResponse
    {
        $org = Organization::where('slug', $slug)
            ->where('is_active', true)   // fix: boolean bukan string
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id'       => $org->id,
                'name'     => $org->name,
                'slug'     => $org->slug,
                'phone'    => $org->phone,
                'address'  => $org->address,
                'city'     => $org->city,
                'logo'     => $org->logo,
                'timezone' => $org->timezone,
                'currency' => $org->currency,
                'opening_hours' => $org->opening_hours,
            ],
        ]);
    }

    /**
     * Scan QR meja → dapatkan public_token.
     * Buat order baru jika belum ada, dengan snapshot tax/service.
     */
    public function scanTable(string $qrToken): JsonResponse
    {
        $table = DiningTable::where('qr_token', $qrToken)
            ->where('is_active', true)   // fix: boolean bukan string
            ->firstOrFail();

        // Cari order aktif di meja ini
        $order = Order::where('dining_table_id', $table->id)
            ->where('bill_status', BillStatus::Open)
            ->latest()
            ->first();

        // Jika belum ada → buat order baru dengan snapshot
        if (! $order) {
            $org = Organization::findOrFail($table->organization_id);

            $taxSnapshot     = $org->tax_enabled ? (float) $org->tax_rate : 0.0;
            $serviceSnapshot = $org->service_charge_enabled ? (float) $org->service_charge_rate : 0.0;

            $order = Order::create([
                'order_number'                 => Order::generateOrderNumber($table->organization_id),
                'public_token'                 => Str::random(32),
                'organization_id'              => $table->organization_id,
                'dining_table_id'              => $table->id,
                'order_type'                   => OrderType::TableOrder,
                'bill_status'                  => BillStatus::Open,
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
                'opened_at'                    => now(),
            ]);
        }

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
                'order' => [
                    'public_token' => $order->public_token,
                    'order_number' => $order->order_number,
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
     * Lihat order saat ini (customer).
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}` yang didapatkan dari scan QR meja.
     */
    public function showOrder(Request $request): JsonResponse
    {
        $order = $request->attributes->get('customer_order');
        $order->load(['items.children', 'diningTable']);

        return response()->json([
            'data' => new OrderDetailResource($order),
        ]);
    }

    /**
     * Tambah item ke order (customer) — snapshot item_type dari menu.type.
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
     */
    public function addItems(AddItemsRequest $request): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

        foreach ($request->items as $itemData) {
            $menu = Menu::where('organization_id', $order->organization_id)
                ->where('is_available', true)   // fix: boolean
                ->findOrFail($itemData['menu_id']);

            $itemType = match ($menu->type->value) {
                'variant' => ItemType::Variant->value,
                'addon'   => ItemType::Addon->value,
                default   => ItemType::Product->value,
            };

            $qty      = $itemData['quantity'];
            $subtotal = round((float) $menu->price * $qty, 2);

            $item = OrderItem::create([
                'order_id'  => $order->id,
                'menu_id'   => $menu->id,
                'item_type' => $itemType,
                'name'      => $menu->name,
                'price'     => $menu->price,
                'quantity'  => $qty,
                'subtotal'  => $subtotal,
                'note'      => $itemData['note'] ?? null,
            ]);

            if (! empty($itemData['children'])) {
                foreach ($itemData['children'] as $childData) {
                    $childMenu = Menu::where('organization_id', $order->organization_id)
                        ->findOrFail($childData['menu_id']);

                    $childItemType = match ($childMenu->type->value) {
                        'variant' => ItemType::Variant->value,
                        'addon'   => ItemType::Addon->value,
                        default   => ItemType::Product->value,
                    };

                    OrderItem::create([
                        'order_id'       => $order->id,
                        'menu_id'        => $childMenu->id,
                        'parent_item_id' => $item->id,
                        'item_type'      => $childItemType,
                        'name'           => $childMenu->name,
                        'price'          => $childMenu->price,
                        'quantity'       => $qty,
                        'subtotal'       => round((float) $childMenu->price * $qty, 2),
                    ]);
                }
            }
        }

        $order->recalculate();

        return response()->json([
            'data'    => new OrderDetailResource($order->fresh()->load('items.children', 'diningTable')),
            'message' => 'Item berhasil ditambahkan.',
        ]);
    }

    /**
     * Customer bayar QRIS.
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
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
                'qr_url'            => $result['qr_url'] ?? null,
                'payment_reference' => $reference,
            ],
            'message' => 'QRIS payment dibuat.',
        ]);
    }

    /**
     * Polling status QRIS.
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
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
     * Cancel QRIS (customer).
     *
     * Endpoint ini memerlukan header `X-Public-Token: {public_token}`.
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
}
