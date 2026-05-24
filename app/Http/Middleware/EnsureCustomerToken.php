<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
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
            ->where('bill_status', 'open')
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Sesi tidak valid atau sudah berakhir.',
            ], 403);
        }

        // Simpan order ke request attributes untuk dipakai controller
        $request->attributes->set('customer_order', $order);

        return $next($request);
    }
}
