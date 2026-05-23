<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\MemberStatus;
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

        // Cari status keanggotaan user di organisasi ini
        $member = OrganizationMember::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$member || $member->status !== MemberStatus::Active) {
            return response()->json([
                'message' => 'Anda bukan member aktif dari organisasi ini.',
            ], 403);
        }

        // Update context dengan data member
        $context->set($organization, $member);

        return $next($request);
    }
}
