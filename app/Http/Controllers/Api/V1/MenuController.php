<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MenuType;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json(['data' => $menus]);
    }

    /**
     * Buat menu item (product, variant_group, variant, addon_group, addon).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:product,variant_group,variant,addon_group,addon',
            'parent_id' => 'nullable|integer|exists:menus,id',
            'name' => 'required|string|max:255',
            'price' => 'numeric|min:0',
            'sort_order' => 'integer|min:0',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();

        // Validasi parent_id harus milik org yang sama
        if ($request->parent_id) {
            $parent = Menu::where('organization_id', $orgId)->findOrFail($request->parent_id);

            // Validasi hierarki
            $this->validateHierarchy($request->type, $parent->type->value);
        }

        $menu = Menu::create([
            'organization_id' => $orgId,
            'parent_id' => $request->parent_id,
            'type' => $request->type,
            'name' => $request->name,
            'price' => $request->input('price', 0),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json([
            'data' => $menu,
            'message' => 'Menu berhasil dibuat.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'sort_order' => 'sometimes|integer|min:0',
            'is_available' => 'sometimes|boolean',
        ]);

        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $menu = Menu::where('organization_id', $orgId)->findOrFail($id);

        $menu->update($request->only(['name', 'price', 'sort_order', 'is_available']));

        return response()->json([
            'data' => $menu->fresh(),
            'message' => 'Menu berhasil diupdate.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $menu = Menu::where('organization_id', $orgId)->findOrFail($id);

        $menu->delete(); // cascade ke children via FK

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }

    /**
     * Toggle is_available.
     */
    public function toggle(int $id): JsonResponse
    {
        $orgId = app(OrganizationContext::class)->getOrganizationId();
        $menu = Menu::where('organization_id', $orgId)->findOrFail($id);

        $menu->update(['is_available' => ! $menu->is_available]);

        return response()->json([
            'data' => $menu->fresh(),
            'message' => $menu->is_available ? 'Menu diaktifkan.' : 'Menu dinonaktifkan.',
        ]);
    }

    /**
     * Validasi hierarki parent-child.
     */
    private function validateHierarchy(string $childType, string $parentType): void
    {
        $allowed = [
            'variant_group' => 'product',
            'addon_group' => 'product',
            'variant' => 'variant_group',
            'addon' => 'addon_group',
        ];

        if (! isset($allowed[$childType])) {
            abort(422, 'Product tidak boleh punya parent.');
        }

        if ($allowed[$childType] !== $parentType) {
            abort(422, "Tipe {$childType} harus berada di dalam {$allowed[$childType]}, bukan {$parentType}.");
        }
    }
}
