<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Events\OrderCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerOrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $session = $request->attributes->get('customer_session');
        if (!$session) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        $bill = $session->bill;
        if (!$bill || $bill->status !== BillStatus::Open) {
            return response()->json(['message' => 'Tidak ada tagihan aktif untuk meja ini.'], 400);
        }

        $order = DB::transaction(function () use ($request, $session, $bill) {
            $totalAmount = 0;

            // Generate order number
            $orderNumber = 'ORD-' . strtoupper(Str::random(6));

            $order = Order::create([
                'organization_id' => $session->organization_id,
                'open_bill_id' => $bill->id,
                'customer_session_id' => $session->id,
                'dining_table_id' => $session->dining_table_id,
                'order_number' => $orderNumber,
                'source' => 'customer',
                'status' => OrderStatus::Pending,
                'note' => $request->note ?? null,
            ]);

            foreach ($request->items as $item) {
                // We don't use BelongsToOrganization scope here directly because the context is already set by middleware
                $menu = Menu::where('id', $item['menu_id'])
                    ->where('organization_id', $session->organization_id)
                    ->where('status', 'active')
                    ->first();

                if (!$menu) {
                    throw new \Exception("Menu ID {$item['menu_id']} tidak ditemukan atau tidak aktif.");
                }

                $subtotal = $menu->price * $item['quantity'];
                $totalAmount += $subtotal;

                OrderItem::create([
                    'organization_id' => $session->organization_id,
                    'order_id' => $order->id,
                    'menu_id' => $menu->id,
                    'menu_name_snapshot' => $menu->name,
                    'menu_price_snapshot' => $menu->price,
                    'quantity' => $item['quantity'],
                    'note' => $item['notes'] ?? null,
                    'status' => 'pending',
                    'subtotal_amount' => $subtotal,
                ]);
            }

            $order->update([
                'subtotal_amount' => $totalAmount,
                'total_amount' => $totalAmount,
            ]);

            // Update open bill amounts
            $bill->subtotal_amount += $totalAmount;
            $bill->total_amount += $totalAmount;
            $bill->save();

            return $order;
        });

        $order->load('items');

        // Broadcast event
        broadcast(new OrderCreated($order));

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => $order
        ], 201);
    }
}
