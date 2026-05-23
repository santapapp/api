<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerMenuController extends Controller
{
    /**
     * Get categorized menus for the customer session.
     */
    public function index(Request $request): JsonResponse
    {
        // Ambil kategori menu aktif
        $categories = MenuCategory::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Ambil menu aktif (available / out_of_stock tetap tampil tapi dengan status yang benar)
        $menus = Menu::where('status', '!=', 'inactive')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Group menus by category
        $grouped = $categories->map(function ($category) use ($menus) {
            $categoryMenus = $menus->where('menu_category_id', $category->id)->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'description' => $menu->description,
                    'price' => $menu->price,
                    'sku' => $menu->sku,
                    'status' => $menu->status,
                    'sort_order' => $menu->sort_order,
                    'image_url' => $menu->getFirstMediaUrl('menu-images') ?: null,
                ];
            });

            return [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'menus' => $categoryMenus->values()->all(),
            ];
        });

        return response()->json([
            'data' => $grouped,
        ]);
    }
}
