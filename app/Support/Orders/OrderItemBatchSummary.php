<?php

declare(strict_types=1);

namespace App\Support\Orders;

use App\Enums\ItemStatus;
use App\Http\Resources\Concerns\NormalizesNumbers;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class OrderItemBatchSummary
{
    use NormalizesNumbers;

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function fromItems(Collection $items, bool $latestFirst = false, bool $includeItems = false): array
    {
        $batches = $items
            ->groupBy(fn (OrderItem $item): string => (string) ($item->batch_uuid ?: 'legacy-'.$item->order_id.'-1'))
            ->map(function (Collection $batchItems) use ($includeItems): array {
                /** @var OrderItem $first */
                $first = $batchItems->sortBy('id')->first();
                $submittedAt = $batchItems
                    ->map(fn (OrderItem $item) => $item->submitted_at ?? $item->created_at)
                    ->filter()
                    ->sort()
                    ->first();
                $batchNumber = $first->batch_number ?? 1;

                return [
                    'batch_uuid' => $first->batch_uuid,
                    'batch_number' => $batchNumber,
                    'label' => 'Pesanan #'.$batchNumber,
                    'submitted_at' => $submittedAt?->toIso8601String(),
                    'items_count' => $batchItems->count(),
                    'total_amount' => self::num($batchItems->sum(fn (OrderItem $item): float => (float) $item->subtotal)),
                    'status' => self::status($batchItems),
                    '_items' => $includeItems ? $batchItems->sortBy('id')->values() : null,
                ];
            })
            ->values()
            ->sortBy([
                ['batch_number', $latestFirst ? 'desc' : 'asc'],
                ['submitted_at', $latestFirst ? 'desc' : 'asc'],
            ])
            ->values();

        return $batches
            ->map(function (array $batch) use ($includeItems): array {
                if (! $includeItems) {
                    unset($batch['_items']);
                }

                return $batch;
            })
            ->all();
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    public static function latest(Collection $items): ?array
    {
        $latest = collect(self::fromItems($items, latestFirst: true))->first();

        return is_array($latest) ? $latest : null;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    public static function count(Collection $items): int
    {
        return count(self::fromItems($items));
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private static function status(Collection $items): string
    {
        $statuses = $items
            ->map(fn (OrderItem $item): ?ItemStatus => $item->item_status)
            ->filter();

        if ($statuses->isEmpty()) {
            return 'pending';
        }

        if ($statuses->every(fn (ItemStatus $status): bool => $status === ItemStatus::Cancelled)) {
            return 'cancelled';
        }

        if ($statuses->every(fn (ItemStatus $status): bool => $status === ItemStatus::Served)) {
            return 'completed';
        }

        if ($statuses->every(fn (ItemStatus $status): bool => $status === ItemStatus::Ready)) {
            return 'ready';
        }

        if ($statuses->contains(fn (ItemStatus $status): bool => $status === ItemStatus::Preparing)) {
            return 'preparing';
        }

        if ($statuses->every(fn (ItemStatus $status): bool => $status === ItemStatus::Pending)) {
            return 'pending';
        }

        return 'partial';
    }
}
