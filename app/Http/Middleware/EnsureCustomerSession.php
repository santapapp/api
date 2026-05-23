<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomerSessionStatus;
use App\Models\CustomerSession;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Clear any existing context
        app(OrganizationContext::class)->clear();

        $sessionToken = $request->header('X-Customer-Session');

        if (empty($sessionToken)) {
            return response()->json([
                'message' => 'Header X-Customer-Session wajib disertakan.',
            ], 401);
        }

        // Cari sesi pelanggan aktif
        $session = CustomerSession::with(['table', 'organization'])
            ->where('session_token', $sessionToken)
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'Sesi tidak ditemukan.',
            ], 401);
        }

        if ($session->status !== CustomerSessionStatus::Active) {
            return response()->json([
                'message' => 'Sesi telah ditutup atau tidak aktif.',
            ], 401);
        }

        if ($session->expires_at->isPast()) {
            $session->update(['status' => CustomerSessionStatus::Expired]);
            return response()->json([
                'message' => 'Sesi Anda telah kedaluwarsa.',
            ], 401);
        }

        // Update last seen
        $session->update(['last_seen_at' => now()]);

        // Resolve context
        app(OrganizationContext::class)->set($session->organization);

        // Simpan sesi di request
        $request->attributes->set('customer_session', $session);

        return $next($request);
    }
}
