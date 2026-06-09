<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderQrisPaymentService;
use App\Services\QrisService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Public-Token');

        if (empty($token)) {
            return response()->json(['message' => 'Token tidak ditemukan.'], 401);
        }

        $order = Order::where('public_token', $token)
            ->where('order_type', OrderType::OpenBill)
            ->where('bill_status', BillStatus::Open)
            ->where('order_status', '!=', OrderStatus::Cancelled)
            ->whereNull('cancelled_at')
            ->whereNull('closed_at')
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Sesi open bill tidak valid atau sudah berakhir.',
            ], 403);
        }

        if (
            $order->payment_status === PaymentStatus::Pending
            && $order->payment_expires_at !== null
            && $order->payment_expires_at->isPast()
        ) {
            $order = app(OrderQrisPaymentService::class)
                ->releaseExpiredPending($order, app(QrisService::class));

            Log::info('EnsureCustomerToken: expired QRIS attempt released', [
                'order_no' => $order->order_number,
                'payment_status_after' => $order->payment_status->value,
            ]);
        }

        $request->attributes->set('customer_order', $order);

        return $next($request);
    }
}
