<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $context = app(OrganizationContext::class);
        $organization = $context->get();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$organization) {
            return response()->json([
                'message' => 'Konteks organisasi belum ditentukan.',
            ], 500);
        }

        // Cek permission dengan team scope dari Spatie Permission
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'message' => 'Anda tidak memiliki hak akses untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
