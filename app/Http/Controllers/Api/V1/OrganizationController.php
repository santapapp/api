<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgs = $request->user()->organizations()->get()->map(fn ($org) => [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'role' => $org->pivot->role,
        ]);

        return response()->json(['data' => $orgs]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:organizations,slug|alpha_dash',
        ]);

        $org = Organization::create([
            'name' => $request->name,
            'slug' => Str::lower($request->slug),
        ]);

        // User otomatis jadi owner
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
        ]);

        return response()->json([
            'data' => [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
            ],
            'message' => 'Organisasi berhasil dibuat.',
        ], 201);
    }
}
