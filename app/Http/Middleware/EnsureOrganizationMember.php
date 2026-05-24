<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OrganizationMember;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(OrganizationContext::class);
        $organization = $context->get();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $organization) {
            return response()->json(['message' => 'Konteks organisasi belum ditentukan.'], 500);
        }

        $member = OrganizationMember::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return response()->json([
                'message' => 'Anda bukan member dari organisasi ini.',
            ], 403);
        }

        // Simpan member ke context untuk dipakai controller
        $context->set($organization, $member);

        return $next($request);
    }
}
