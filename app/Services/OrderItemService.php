<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ItemType;
use App\Enums\MenuType;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItemService
{
    /**
     * Tambah satu atau lebih item ke order.
     * Seluruh proses dibungkus dalam DB transaction.
     *
     * @param  Order  $order
     * @param  array  $items  — validated array dari AddItemsRequest
     * @throws ValidationException
     */
    public function addItems(Order $order, array $items): void
    {
        DB::transaction(function () use ($order, $items): void {
            foreach ($items as $index => $itemData) {
                $this->processItem($order, $itemData, $index);
            }

            $order->recalculate();
        });
    }

    /**
     * Proses satu item: validasi, kalkulasi harga, simpan snapshot.
     */
    private function processItem(Order $order, array $itemData, int $index): void
    {
        // ── 1. Load product dengan semua variant groups dan variants ──────────
        $product = Menu::with([
            'children' => function ($q) {
                // Hanya variant_group yang available
                $q->where('type', MenuType::VariantGroup)
                  ->where('is_available', true)
                  ->with(['children' => function ($q2) {
                      // Hanya variant yang available
                      $q2->where('type', MenuType::Variant)
                         ->where('is_available', true);
                  }]);
            },
        ])->find($itemData['menu_id']);

        // ── 2. Validasi: menu harus ada, type product, available, & org sama ──
        if (! $product) {
            throw ValidationException::withMessages([
                "items.{$index}.menu_id" => ['Menu tidak ditemukan.'],
            ]);
        }

        if ($product->type !== MenuType::Product) {
            throw ValidationException::withMessages([
                "items.{$index}.menu_id" => [
                    "Menu '{$product->name}' bukan type product. Hanya produk utama yang bisa dipesan.",
                ],
            ]);
        }

        if (! $product->is_available) {
            throw ValidationException::withMessages([
                "items.{$index}.menu_id" => ["Menu '{$product->name}' sedang tidak tersedia."],
            ]);
        }

        if ($product->organization_id !== $order->organization_id) {
            throw ValidationException::withMessages([
                "items.{$index}.menu_id" => ['Menu tidak ditemukan di organisasi ini.'],
            ]);
        }

        // ── 3. Index variant groups dari product ──────────────────────────────
        // $variantGroupsById: [ group_id => Menu(variant_group) with children(variants) ]
        $variantGroupsById = $product->children->keyBy('id');

        // ── 4. Proses selected_variants ───────────────────────────────────────
        $selectedVariantData = $itemData['selected_variants'] ?? [];
        $validatedVariants   = [];
        $variantTotal        = 0.0;

        // Kelompokkan pilihan per group_id
        $selectedByGroupId = [];
        foreach ($selectedVariantData as $vIndex => $sel) {
            $groupId   = $sel['variant_group_id'];
            $variantId = $sel['variant_id'];

            // Validasi: group harus child dari product ini
            if (! $variantGroupsById->has($groupId)) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_variants.{$vIndex}.variant_group_id" => [
                        'Variant group tidak ditemukan pada produk ini.',
                    ],
                ]);
            }

            $group    = $variantGroupsById->get($groupId);
            $variants = $group->children->keyBy('id');

            // Validasi: variant harus child dari group ini
            if (! $variants->has($variantId)) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_variants.{$vIndex}.variant_id" => [
                        'Variant tidak ditemukan pada group ini.',
                    ],
                ]);
            }

            $variant       = $variants->get($variantId);
            $variantPrice  = (float) $variant->price;
            $variantTotal += $variantPrice;

            $validatedVariants[] = [
                'group'        => $group,
                'variant'      => $variant,
                'variantPrice' => $variantPrice,
            ];

            $selectedByGroupId[$groupId][] = $variantId;
        }

        // ── 5. Validasi is_required, min_select, max_select per group ─────────
        foreach ($variantGroupsById as $groupId => $group) {
            $selectedCount = count($selectedByGroupId[$groupId] ?? []);

            if ($group->is_required && $selectedCount === 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_variants" => [
                        "Pilihan '{$group->name}' wajib dipilih.",
                    ],
                ]);
            }

            if ($selectedCount < $group->min_select) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_variants" => [
                        "Pilihan '{$group->name}' minimal {$group->min_select} item.",
                    ],
                ]);
            }

            if ($group->max_select > 0 && $selectedCount > $group->max_select) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_variants" => [
                        "Pilihan '{$group->name}' maksimal {$group->max_select} item.",
                    ],
                ]);
            }
        }

        // ── 6. Kalkulasi harga — BACKEND ONLY ─────────────────────────────────
        $basePrice    = round((float) $product->price, 2);
        $variantTotal = round($variantTotal, 2);
        $unitPrice    = round($basePrice + $variantTotal, 2);
        $qty          = (int) $itemData['quantity'];
        $subtotal     = round($unitPrice * $qty, 2);

        // Support both 'note' and 'notes' temporarily
        $note = $itemData['notes'] ?? $itemData['note'] ?? null;

        // ── 7. Bangun snapshot selected_options untuk metadata ────────────────
        $selectedOptions = [];
        foreach ($validatedVariants as $v) {
            $selectedOptions[] = [
                'group_id'    => $v['group']->id,
                'group_name'  => $v['group']->name,
                'option_id'   => $v['variant']->id,
                'option_name' => $v['variant']->name,
                'price_delta' => $v['variantPrice'],
            ];
        }

        // ── 8. Simpan OrderItem dengan snapshot ───────────────────────────────
        OrderItem::create([
            'order_id'      => $order->id,
            'menu_id'       => $product->id,
            'item_type'     => ItemType::Product->value,
            'name'          => $product->name,
            'base_price'    => $basePrice,
            'variant_total' => $variantTotal,
            'unit_price'    => $unitPrice,
            'price'         => $unitPrice,   // legacy field — sama dengan unit_price
            'quantity'      => $qty,
            'subtotal'      => $subtotal,
            'note'          => $note,
            'metadata'      => empty($selectedOptions) ? null : ['selected_options' => $selectedOptions],
        ]);
    }
}
