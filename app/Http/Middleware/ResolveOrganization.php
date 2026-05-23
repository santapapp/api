<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        // Clear any existing context (important for state isolation between requests)
        app(OrganizationContext::class)->clear();

        $orgIdentifier = $request->header('X-Organization-Id');

        if (empty($orgIdentifier)) {
            return response()->json([
                'message' => 'Header X-Organization-Id wajib disertakan.',
            ], 400);
        }

        // Cari berdasarkan UUID (format UUID v4) atau ID numerik.
        // Penting: jangan cast string non-UUID ke kolom uuid PostgreSQL karena akan error.
        $isUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $orgIdentifier);

        if ($isUuid) {
            $organization = Organization::where('uuid', $orgIdentifier)->first();
        } elseif (is_numeric($orgIdentifier)) {
            $organization = Organization::where('id', (int) $orgIdentifier)->first();
        } else {
            $organization = null;
        }

        if (!$organization) {
            return response()->json([
                'message' => 'Organisasi tidak ditemukan.',
            ], 404);
        }

        if ($organization->status !== OrganizationStatus::Active) {
            return response()->json([
                'message' => 'Organisasi sedang ditangguhkan atau tidak aktif.',
            ], 403);
        }

        // Set context
        app(OrganizationContext::class)->set($organization);

        return $next($request);
    }
}
