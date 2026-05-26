<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MenuType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\StoreMenuRequest;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    /**
     * List menu tree: products + children (eager loaded 2 levels deep).
     */
    public function index(): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        $menus = Menu::where('organization_id', $orgId)
            ->products()
            ->with('children.children')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => MenuResource::collection($menus),
        ]);
    }

    /**
     * Buat menu item baru (product, variant_group, variant, addon_group, addon).
     */
    public function store(StoreMenuRequest $request): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();

        // Validasi parent_id milik org yang sama & cek hierarki
        if ($request->parent_id) {
            $parent = Menu::where('organization_id', $orgId)->findOrFail($request->parent_id);
            $this->validateHierarchy($request->type, $parent->type->value);
        }

        $menu = Menu::create([
            'organization_id' => $orgId,
            'parent_id'       => $request->parent_id,
            'type'            => $request->type,
            'name'            => $request->name,
            'image'           => $request->image,
            'sku'             => $request->sku,
            'description'     => $request->description,
            'price'           => $request->input('price', 0),
            'is_available'    => $request->input('is_available', true),
            'is_required'     => $request->input('is_required', false),
            'min_select'      => $request->input('min_select', 0),
            'max_select'      => $request->input('max_select', 1),
            'sort_order'      => $request->input('sort_order', 0),
            'metadata'        => $request->metadata,
        ]);

        return response()->json([
            'data'    => new MenuResource($menu),
            'message' => 'Menu berhasil dibuat.',
        ], 201);
    }

    public function update(UpdateMenuRequest $request, int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $menu  = Menu::where('organization_id', $orgId)->findOrFail($id);

        $menu->update($request->validated());

        return response()->json([
            'data'    => new MenuResource($menu->fresh()->load('children.children')),
            'message' => 'Menu berhasil diupdate.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $menu  = Menu::where('organization_id', $orgId)->findOrFail($id);
        $menu->delete(); // cascade ke children via FK

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }

    /**
     * Toggle is_available.
     */
    public function toggle(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $menu  = Menu::where('organization_id', $orgId)->findOrFail($id);
        $menu->update(['is_available' => ! $menu->is_available]);

        return response()->json([
            'data'    => new MenuResource($menu->fresh()),
            'message' => $menu->is_available ? 'Menu diaktifkan.' : 'Menu dinonaktifkan.',
        ]);
    }

    /**
     * Validasi hierarki parent-child yang diizinkan.
     */
    private function validateHierarchy(string $childType, string $parentType): void
    {
        $allowed = [
            'variant_group' => 'product',
            'addon_group'   => 'product',
            'variant'       => 'variant_group',
            'addon'         => 'addon_group',
        ];

        if (! isset($allowed[$childType])) {
            abort(422, 'Product tidak boleh punya parent.');
        }

        if ($allowed[$childType] !== $parentType) {
            abort(422, "Tipe {$childType} harus berada di dalam {$allowed[$childType]}, bukan {$parentType}.");
        }
    }
}
