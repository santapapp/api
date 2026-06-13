<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\MenuType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderItemService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function addItems(Order $order, array $items): ?array
    {
        if ($order->order_type === OrderType::OpenBill) {
            return $this->addOpenBillBatchItems($order, $items);
        }

        DB::transaction(function () use ($order, $items): void {
            foreach ($items as $index => $itemData) {
                $this->processItem($order, $itemData, $index);
            }

            $order->recalculate();
            $this->syncOpenBillKitchenStatus($order);
        });

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{batch_uuid: string, batch_number: int, submitted_at: string, items_count: int, batch_total: float}
     */
    public function addOpenBillBatchItems(Order $order, array $items): array
    {
        return DB::transaction(function () use ($order, $items): array {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $batchNumber = ((int) $lockedOrder->items()->max('batch_number')) + 1;
            $submittedAt = now();
            $batch = [
                'batch_uuid' => (string) Str::uuid(),
                'batch_number' => $batchNumber,
                'submitted_at' => $submittedAt,
            ];
            $createdItems = collect();

            foreach ($items as $index => $itemData) {
                $createdItems->push($this->processItem($lockedOrder, $itemData, $index, $batch));
            }

            $lockedOrder->recalculate();
            $this->syncOpenBillKitchenStatus($lockedOrder);

            return [
                'batch_uuid' => $batch['batch_uuid'],
                'batch_number' => $batchNumber,
                'submitted_at' => $submittedAt->toIso8601String(),
                'items_count' => $createdItems->count(),
                'batch_total' => round($createdItems->sum(fn (OrderItem $item): float => (float) $item->subtotal), 2),
            ];
        });
    }

    public function updateQuantity(Order $order, OrderItem $item, int $quantity): void
    {
        DB::transaction(function () use ($order, $item, $quantity): void {
            $lockedItem = OrderItem::where('order_id', $order->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->item_status !== ItemStatus::Pending) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity hanya bisa diubah untuk item yang masih pending.',
                ]);
            }

            if ($lockedItem->parent_item_id !== null) {
                throw ValidationException::withMessages([
                    'quantity' => 'Quantity hanya bisa diubah pada item utama.',
                ]);
            }

            $lockedItem->update(['quantity' => $quantity]);
            $lockedItem->syncSubtotal();

            $order->recalculate();
            $this->syncOpenBillKitchenStatus($order);
        });
    }

    public function removeItem(Order $order, OrderItem $item): void
    {
        DB::transaction(function () use ($order, $item): void {
            $lockedItem = OrderItem::where('order_id', $order->id)
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->item_status !== ItemStatus::Pending) {
                throw ValidationException::withMessages([
                    'item' => 'Item hanya bisa dihapus saat masih pending.',
                ]);
            }

            $lockedItem->delete();

            $order->recalculate();
            $this->syncOpenBillKitchenStatus($order);
        });
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function processItem(Order $order, array $itemData, int $index, ?array $batch = null): OrderItem
    {
        $product = Menu::with([
            'children' => function ($query): void {
                $query->whereIn('type', [MenuType::VariantGroup, MenuType::AddonGroup])
                    ->where('is_available', true)
                    ->with(['children' => function ($childQuery): void {
                        $childQuery->whereIn('type', [MenuType::Variant, MenuType::Addon])
                            ->where('is_available', true)
                            ->orderBy('sort_order');
                    }])
                    ->orderBy('sort_order');
            },
        ])->find($itemData['menu_id']);

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

        $optionGroupsById = $product->children->keyBy('id');
        $selectedOptionData = $this->normalizeSelectedOptions($itemData);
        $validatedOptions = [];
        $optionTotal = 0.0;
        $selectedByGroupId = [];

        foreach ($selectedOptionData as $optionIndex => $selection) {
            $groupId = $selection['group_id'];
            $optionId = $selection['option_id'];

            if (! $optionGroupsById->has($groupId)) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_options.{$optionIndex}.group_id" => [
                        'Option group tidak ditemukan pada produk ini.',
                    ],
                ]);
            }

            $group = $optionGroupsById->get($groupId);
            $options = $group->children->keyBy('id');

            if (! $options->has($optionId)) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_options.{$optionIndex}.option_id" => [
                        'Option tidak ditemukan pada group ini.',
                    ],
                ]);
            }

            $option = $options->get($optionId);
            $optionPrice = (float) $option->price;
            $optionTotal += $optionPrice;

            $validatedOptions[] = [
                'group' => $group,
                'option' => $option,
                'optionPrice' => $optionPrice,
            ];

            $selectedByGroupId[$groupId][] = $optionId;
        }

        foreach ($optionGroupsById as $groupId => $group) {
            $selectedCount = count($selectedByGroupId[$groupId] ?? []);

            if ($group->is_required && $selectedCount === 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_options" => [
                        "Pilihan '{$group->name}' wajib dipilih.",
                    ],
                ]);
            }

            if ($selectedCount < $group->min_select) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_options" => [
                        "Pilihan '{$group->name}' minimal {$group->min_select} item.",
                    ],
                ]);
            }

            if ($group->max_select > 0 && $selectedCount > $group->max_select) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_options" => [
                        "Pilihan '{$group->name}' maksimal {$group->max_select} item.",
                    ],
                ]);
            }
        }

        $basePrice = round((float) $product->price, 2);
        $optionTotal = round($optionTotal, 2);
        $unitPrice = round($basePrice + $optionTotal, 2);
        $quantity = (int) $itemData['quantity'];
        $subtotal = round($unitPrice * $quantity, 2);
        $note = $itemData['notes'] ?? $itemData['note'] ?? null;

        $selectedOptions = [];
        foreach ($validatedOptions as $validatedOption) {
            $selectedOptions[] = [
                'group_id' => $validatedOption['group']->id,
                'group_name' => $validatedOption['group']->name,
                'group_type' => $validatedOption['group']->type->value,
                'option_id' => $validatedOption['option']->id,
                'option_name' => $validatedOption['option']->name,
                'option_type' => $validatedOption['option']->type->value,
                'price_delta' => $validatedOption['optionPrice'],
            ];
        }

        return OrderItem::create([
            'order_id' => $order->id,
            'menu_id' => $product->id,
            'item_type' => ItemType::Product->value,
            'name' => $product->name,
            'base_price' => $basePrice,
            'variant_total' => $optionTotal,
            'unit_price' => $unitPrice,
            'price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'note' => $note,
            'metadata' => empty($selectedOptions) ? null : ['selected_options' => $selectedOptions],
            'batch_uuid' => $batch['batch_uuid'] ?? null,
            'batch_number' => $batch['batch_number'] ?? null,
            'submitted_at' => $batch['submitted_at'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $itemData
     * @return array<int, array{group_id: mixed, option_id: mixed}>
     */
    private function normalizeSelectedOptions(array $itemData): array
    {
        $selected = $itemData['selected_options'] ?? null;

        if (is_array($selected)) {
            return array_map(fn (array $option): array => [
                'group_id' => $option['group_id'] ?? $option['variant_group_id'] ?? null,
                'option_id' => $option['option_id'] ?? $option['variant_id'] ?? null,
            ], $selected);
        }

        $legacy = $itemData['selected_variants'] ?? [];

        return array_map(fn (array $option): array => [
            'group_id' => $option['variant_group_id'] ?? null,
            'option_id' => $option['variant_id'] ?? null,
        ], is_array($legacy) ? $legacy : []);
    }

    private function syncOpenBillKitchenStatus(Order $order): void
    {
        if ($order->order_type !== OrderType::OpenBill) {
            return;
        }

        if ($order->order_status === OrderStatus::Pending) {
            $order->update(['order_status' => OrderStatus::Confirmed]);
        }

        $order->syncStatusFromItems();
    }
}
