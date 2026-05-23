<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = MenuCategory::orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Str::slug($request->name);

        // Check if slug is unique per organization
        $exists = MenuCategory::where('slug', $slug)->exists();
        if ($exists) {
            $slug = $slug . '-' . Str::lower(Str::random(4));
        }

        $category = MenuCategory::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'message' => 'Kategori menu berhasil dibuat.',
            'data' => $category,
        ], 201);
    }

    public function show(int $menuCategory): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $category = MenuCategory::where('id', $menuCategory)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => $category,
        ]);
    }

    public function update(Request $request, int $menuCategory): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $menuCategory = MenuCategory::where('id', $menuCategory)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$menuCategory) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = $menuCategory->slug;
        if ($request->name !== $menuCategory->name) {
            $slug = Str::slug($request->name);
            $exists = MenuCategory::where('slug', $slug)
                ->where('id', '!=', $menuCategory->id)
                ->exists();
            if ($exists) {
                $slug = $slug . '-' . Str::lower(Str::random(4));
            }
        }

        $menuCategory->update([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Kategori menu berhasil diperbarui.',
            'data' => $menuCategory,
        ]);
    }

    public function destroy(int $menuCategory): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $category = MenuCategory::where('id', $menuCategory)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori menu berhasil dihapus.',
        ]);
    }
}
