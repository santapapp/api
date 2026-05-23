<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Menu::with('category')->orderBy('sort_order')->orderBy('name');

        if ($request->has('category_id')) {
            $query->where('menu_category_id', $request->category_id);
        }

        $menus = $query->get()->map(fn ($menu) => $this->transformMenu($menu));

        return response()->json([
            'data' => $menus,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'menu_category_id' => 'required|exists:menu_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,out_of_stock',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Str::slug($request->name);
        $exists = Menu::where('slug', $slug)->exists();
        if ($exists) {
            $slug = $slug . '-' . Str::lower(Str::random(4));
        }

        $menu = Menu::create([
            'menu_category_id' => $request->menu_category_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'sku' => $request->sku,
            'status' => $request->status ?? 'active',
            'sort_order' => $request->sort_order ?? 0,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'message' => 'Menu berhasil dibuat.',
            'data' => $this->transformMenu($menu),
        ], 201);
    }

    public function show(int $menu): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $menuModel = Menu::where('id', $menu)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$menuModel) {
            return response()->json(['message' => 'Menu tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => $this->transformMenu($menuModel),
        ]);
    }

    public function update(Request $request, int $menu): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $menu = Menu::where('id', $menu)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$menu) {
            return response()->json(['message' => 'Menu tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu_category_id' => 'required|exists:menu_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|max:255',
            'status' => 'required|string|in:active,inactive,out_of_stock',
            'sort_order' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = $menu->slug;
        if ($request->name !== $menu->name) {
            $slug = Str::slug($request->name);
            $exists = Menu::where('slug', $slug)
                ->where('id', '!=', $menu->id)
                ->exists();
            if ($exists) {
                $slug = $slug . '-' . Str::lower(Str::random(4));
            }
        }

        $menu->update([
            'menu_category_id' => $request->menu_category_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'price' => $request->price,
            'sku' => $request->sku,
            'status' => $request->status,
            'sort_order' => $request->sort_order ?? 0,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'message' => 'Menu berhasil diperbarui.',
            'data' => $this->transformMenu($menu),
        ]);
    }

    public function destroy(int $menu): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $menuModel = Menu::where('id', $menu)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$menuModel) {
            return response()->json(['message' => 'Menu tidak ditemukan.'], 404);
        }

        $menuModel->delete();

        return response()->json([
            'message' => 'Menu berhasil dihapus.',
        ]);
    }

    public function uploadImage(Request $request, int $menu): JsonResponse
    {
        $context = app(\App\Services\OrganizationContext::class);
        $menuModel = Menu::where('id', $menu)
            ->where('organization_id', $context->getOrganizationId())
            ->first();

        if (!$menuModel) {
            return response()->json(['message' => 'Menu tidak ditemukan.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Clear existing media in the collection
        $menuModel->clearMediaCollection('menu-images');

        // Add new media
        $media = $menuModel->addMediaFromRequest('image')->toMediaCollection('menu-images');

        return response()->json([
            'message' => 'Gambar menu berhasil diunggah.',
            'image_url' => $media->getUrl(),
            'data' => $this->transformMenu($menuModel),
        ]);
    }

    private function transformMenu(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'organization_id' => $menu->organization_id,
            'menu_category_id' => $menu->menu_category_id,
            'category_name' => $menu->category?->name,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'description' => $menu->description,
            'price' => $menu->price,
            'sku' => $menu->sku,
            'status' => $menu->status,
            'sort_order' => $menu->sort_order,
            'metadata' => $menu->metadata,
            'image_url' => $menu->getFirstMediaUrl('menu-images') ?: null,
            'created_at' => $menu->created_at,
            'updated_at' => $menu->updated_at,
        ];
    }
}
