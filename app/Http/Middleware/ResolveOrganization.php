<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        app(OrganizationContext::class)->clear();

        $orgId = $request->header('X-Org-ID');

        if (empty($orgId)) {
            return response()->json([
                'message' => 'Header X-Org-ID wajib disertakan.',
            ], 400);
        }

        $organization = Organization::find((int) $orgId);

        if (! $organization) {
            return response()->json([
                'message' => 'Organisasi tidak ditemukan.',
            ], 404);
        }

        if (! $organization->is_active) {
            return response()->json([
                'message' => 'Organisasi tidak aktif.',
            ], 403);
        }

        app(OrganizationContext::class)->set($organization);

        return $next($request);
    }
}
