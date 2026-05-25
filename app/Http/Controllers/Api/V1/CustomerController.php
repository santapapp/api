<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrderItem;
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
            ->where('is_active', 'true')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
            ]
        ]);
    }

    /**
     * Scan QR meja → dapatkan public_token.
     */
    public function scanTable(string $qrToken): JsonResponse
    {
        $table = DiningTable::where('qr_token', $qrToken)
            ->where('is_active', 'true')
            ->firstOrFail();

        // Cari order aktif di meja ini
        $order = Order::where('dining_table_id', $table->id)
            ->where('bill_status', BillStatus::Open)
            ->latest()
            ->first();

        // Jika belum ada → buat order baru
        if (! $order) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber($table->organization_id),
                'public_token' => Str::random(32),
                'organization_id' => $table->organization_id,
                'dining_table_id' => $table->id,
                'order_type' => OrderType::TableOrder,
                'bill_status' => BillStatus::Open,
                'order_status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'opened_at' => now(),
            ]);
        }

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
                ],
                'order' => [
                    'public_token' => $order->public_token,
                    'order_number' => $order->order_number,
                ],
            ],
        ]);
    }

    /**
     * Menu publik (tanpa auth).
     */
    public function menu(Request $request): JsonResponse
    {
        $request->validate([
            'org' => 'required|integer|exists:organizations,id',
        ]);

        $menus = Menu::where('organization_id', $request->org)
            ->products()
            ->available()
            ->with(['children' => function ($q) {
                $q->where('is_available', 'true')
                    ->with(['children' => function ($q2) {
                        $q2->where('is_available', 'true')->orderBy('sort_order');
                    }])
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $menus]);
    }

    /**
     * Lihat order saat ini (customer).
     */
    public function showOrder(Request $request): JsonResponse
    {
        $order = $request->attributes->get('customer_order');
        $order->load(['items.children', 'diningTable']);

        return response()->json(['data' => $order]);
    }

    /**
     * Tambah item ke order (customer).
     */
    public function addItems(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer|exists:menus,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.note' => 'nullable|string|max:500',
            'items.*.children' => 'nullable|array',
            'items.*.children.*.menu_id' => 'required|integer|exists:menus,id',
        ]);

        $order = $request->attributes->get('customer_order');

        foreach ($request->items as $itemData) {
            $menu = Menu::where('organization_id', $order->organization_id)
                ->where('is_available', 'true')
                ->findOrFail($itemData['menu_id']);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'menu_id' => $menu->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'quantity' => $itemData['quantity'],
                'note' => $itemData['note'] ?? null,
            ]);

            if (! empty($itemData['children'])) {
                foreach ($itemData['children'] as $childData) {
                    $childMenu = Menu::where('organization_id', $order->organization_id)
                        ->findOrFail($childData['menu_id']);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_id' => $childMenu->id,
                        'parent_item_id' => $item->id,
                        'name' => $childMenu->name,
                        'price' => $childMenu->price,
                        'quantity' => $itemData['quantity'],
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
     * Customer bayar QRIS.
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
     * Polling status QRIS.
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
                'bill_status' => BillStatus::Closed,
                'order_status' => OrderStatus::Confirmed,
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
    public function qrisCancel(Request $request, QrisService $qris): JsonResponse
    {
        $order = $request->attributes->get('customer_order');

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
}
