<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ItemStatus;
use App\Enums\ItemType;
use App\Enums\MenuType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Menu;
use App\Models\Organization;
use App\Support\Reports\ReportDateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    /**
     * Financial summary uses paid_at for revenue and cancelled_at for cancelled orders.
     *
     * @return array<string, mixed>
     */
    public function financialSummary(Organization $organization, ReportDateRange $range, string $groupBy): array
    {
        $paidOrders = $this->paidOrders($organization, $range);

        $totals = (clone $paidOrders)
            ->selectRaw(
                'COUNT(*) as total_transactions,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(SUM(subtotal_amount), 0) as total_subtotal,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(SUM(service_charge_amount), 0) as total_service_charge'
            )
            ->first();

        $typeCounts = $this->transactionCountByType((clone $paidOrders));
        $paymentBreakdown = $this->paymentMethodBreakdown((clone $paidOrders));
        $cancelled = $this->cancelledSummary($organization, $range);
        $breakdown = $this->financialBreakdown((clone $paidOrders), $range, $groupBy);

        $serviceCharge = $this->money($totals->total_service_charge ?? 0);

        return [
            'summary' => [
                'total_revenue' => $this->money($totals->total_revenue ?? 0),
                'total_subtotal' => $this->money($totals->total_subtotal ?? 0),
                'total_discount' => $this->money($totals->total_discount ?? 0),
                'total_tax' => $this->money($totals->total_tax ?? 0),
                'total_service_charge' => $serviceCharge,
                'service_charge_total' => $serviceCharge,
                'total_transactions' => (int) ($totals->total_transactions ?? 0),
                'transaction_count_by_type' => $typeCounts,
                'payment_method_breakdown' => $paymentBreakdown,
                'cancelled_transactions' => $cancelled,
            ],
            'breakdown' => $breakdown,
            'meta' => $this->meta($range, [
                'group_by' => $groupBy,
                'revenue_date_basis' => 'paid_at',
                'cancelled_date_basis' => 'cancelled_at',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productBestsellers(Organization $organization, ReportDateRange $range, int $limit): array
    {
        $products = $this->validProductItems($organization, $range)
            ->leftJoin('menus', 'menus.id', '=', 'order_items.menu_id')
            ->selectRaw(
                'order_items.menu_id as id,
                COALESCE(menus.name, order_items.name) as name,
                COALESCE(SUM(order_items.quantity), 0) as total_qty,
                COALESCE(SUM(order_items.subtotal), 0) as total_revenue'
            )
            ->groupByRaw('order_items.menu_id, COALESCE(menus.name, order_items.name)')
            ->orderByDesc('total_qty')
            ->orderByDesc('total_revenue')
            ->orderByRaw('order_items.menu_id NULLS LAST')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id !== null ? (int) $row->id : null,
                'name' => (string) $row->name,
                'total_qty' => (int) $row->total_qty,
                'total_revenue' => $this->money($row->total_revenue),
            ])
            ->values()
            ->all();

        return [
            'products' => $products,
            'meta' => $this->meta($range, [
                'limit' => $limit,
                'item_revenue_rule' => 'Root product order_items subtotal; selected variants/addons are included in the parent item subtotal snapshot.',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productsNoSales(Organization $organization, ReportDateRange $range): array
    {
        $soldProducts = $this->validProductItems($organization, $range)
            ->whereNotNull('order_items.menu_id')
            ->selectRaw('order_items.menu_id')
            ->groupBy('order_items.menu_id');

        $lastSold = $this->validProductItemsUntil($organization, $range->endUtc)
            ->whereNotNull('order_items.menu_id')
            ->selectRaw('order_items.menu_id, MAX(orders.paid_at) as last_sold_at')
            ->groupBy('order_items.menu_id');

        $products = DB::table('menus')
            ->leftJoinSub($soldProducts, 'sold_products', function ($join): void {
                $join->on('sold_products.menu_id', '=', 'menus.id');
            })
            ->leftJoinSub($lastSold, 'last_sold', function ($join): void {
                $join->on('last_sold.menu_id', '=', 'menus.id');
            })
            ->where('menus.organization_id', $organization->id)
            ->where('menus.type', MenuType::Product->value)
            ->whereNull('menus.parent_id')
            ->where('menus.is_available', true)
            ->whereNull('sold_products.menu_id')
            ->selectRaw('menus.id, menus.name, menus.price, last_sold.last_sold_at')
            ->orderBy('menus.name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'price' => $this->money($row->price),
                'last_sold_date' => $row->last_sold_at !== null
                    ? CarbonImmutable::parse((string) $row->last_sold_at, 'UTC')
                        ->timezone($range->timezone)
                        ->toDateString()
                    : null,
            ])
            ->values()
            ->all();

        return [
            'products' => $products,
            'meta' => $this->meta($range, [
                'catalog_scope' => 'Available root product menus in the active organization.',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productSalesByCategory(Organization $organization, ReportDateRange $range): array
    {
        $row = $this->validProductItems($organization, $range)
            ->selectRaw(
                'COALESCE(SUM(order_items.quantity), 0) as total_qty,
                COALESCE(SUM(order_items.subtotal), 0) as total_revenue'
            )
            ->first();

        $revenue = $this->money($row->total_revenue ?? 0);

        return [
            'categories' => [
                [
                    'id' => null,
                    'name' => 'Uncategorized',
                    'total_qty' => (int) ($row->total_qty ?? 0),
                    'total_revenue' => $revenue,
                    'percentage' => $revenue > 0 ? 100.0 : 0.0,
                ],
            ],
            'meta' => $this->meta($range, [
                'category_rule' => 'Current schema has no menu category relation; all product sales are grouped into Uncategorized.',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productTrend(Organization $organization, ReportDateRange $range, int $productId): array
    {
        $product = Menu::query()
            ->where('organization_id', $organization->id)
            ->where('type', MenuType::Product->value)
            ->whereNull('parent_id')
            ->findOrFail($productId);

        [$periodExpression, $bindings] = $this->localPeriodExpression('orders.paid_at', 'daily', $range);

        $rows = $this->validProductItems($organization, $range)
            ->where('order_items.menu_id', $product->id)
            ->selectRaw(
                "{$periodExpression} as period,
                COALESCE(SUM(order_items.quantity), 0) as qty,
                COALESCE(SUM(order_items.subtotal), 0) as revenue",
                $bindings,
            )
            ->groupByRaw('1')
            ->orderBy('period')
            ->get()
            ->keyBy(fn (object $row): string => $this->dateKey($row->period));

        $trend = collect($range->periodKeys('daily'))
            ->map(function (string $date) use ($rows): array {
                $row = $rows->get($date);

                return [
                    'date' => $date,
                    'qty' => $row !== null ? (int) $row->qty : 0,
                    'revenue' => $row !== null ? $this->money($row->revenue) : 0,
                ];
            })
            ->values()
            ->all();

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'current_price' => $this->money($product->price),
            ],
            'trend' => $trend,
            'meta' => $this->meta($range, [
                'date_basis' => 'paid_at',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function operationalByCashier(Organization $organization, ReportDateRange $range): array
    {
        $memberIdExpression = 'CASE WHEN orders.created_by IS NULL OR organization_members.id IS NULL THEN NULL ELSE users.id END';
        $memberNameExpression = "CASE WHEN orders.created_by IS NULL OR organization_members.id IS NULL THEN 'Unassigned' ELSE users.name END";

        $cashiers = $this->paidOrders($organization, $range)
            ->leftJoin('users', 'users.id', '=', 'orders.created_by')
            ->leftJoin('organization_members', function ($join) use ($organization): void {
                $join->on('organization_members.user_id', '=', 'orders.created_by')
                    ->where('organization_members.organization_id', '=', $organization->id);
            })
            ->selectRaw(
                "{$memberIdExpression} as id,
                {$memberNameExpression} as name,
                COUNT(*) as total_transactions,
                COALESCE(SUM(orders.total_amount), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN orders.payment_method = 'cash' THEN orders.total_amount ELSE 0 END), 0) as cash_amount,
                COALESCE(SUM(CASE WHEN orders.payment_method = 'qris' THEN orders.total_amount ELSE 0 END), 0) as qris_amount"
            )
            ->groupByRaw("{$memberIdExpression}, {$memberNameExpression}")
            ->orderByDesc('total_revenue')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->id !== null ? (int) $row->id : null,
                'name' => (string) $row->name,
                'total_transactions' => (int) $row->total_transactions,
                'total_revenue' => $this->money($row->total_revenue),
                'cash_amount' => $this->money($row->cash_amount),
                'qris_amount' => $this->money($row->qris_amount),
            ])
            ->values()
            ->all();

        return [
            'cashiers' => $cashiers,
            'meta' => $this->meta($range, [
                'cashier_basis' => 'orders.created_by; paid_by/closed_by fields do not exist in the current orders schema.',
                'unassigned_rule' => 'Paid self-service orders or rows whose creator is not an organization member are grouped as Unassigned.',
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function operationalPeakHours(Organization $organization, ReportDateRange $range): array
    {
        [$hourExpression, $bindings] = $this->localHourExpression('orders.paid_at', $range);

        $rows = $this->paidOrders($organization, $range)
            ->selectRaw(
                "{$hourExpression} as hour,
                COUNT(*) as transactions,
                COALESCE(SUM(total_amount), 0) as revenue",
                $bindings,
            )
            ->groupByRaw('1')
            ->orderBy('hour')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->hour);

        $hours = collect(range(0, 23))
            ->map(function (int $hour) use ($rows): array {
                $row = $rows->get($hour);

                return [
                    'hour' => $hour,
                    'transactions' => $row !== null ? (int) $row->transactions : 0,
                    'revenue' => $row !== null ? $this->money($row->revenue) : 0,
                ];
            })
            ->all();

        return [
            'hours' => $hours,
            'meta' => $this->meta($range, [
                'date_basis' => 'paid_at',
            ]),
        ];
    }

    private function paidOrders(Organization $organization, ReportDateRange $range): Builder
    {
        return DB::table('orders')
            ->where('orders.organization_id', $organization->id)
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$range->startUtc, $range->endUtc]);
    }

    private function cancelledOrders(Organization $organization, ReportDateRange $range): Builder
    {
        return DB::table('orders')
            ->where('orders.organization_id', $organization->id)
            ->where(function (Builder $query): void {
                $query->where('orders.order_status', OrderStatus::Cancelled->value)
                    ->orWhere('orders.payment_status', PaymentStatus::Cancelled->value);
            })
            ->whereNotNull('orders.cancelled_at')
            ->whereBetween('orders.cancelled_at', [$range->startUtc, $range->endUtc]);
    }

    private function validProductItems(Organization $organization, ReportDateRange $range): Builder
    {
        return $this->validProductItemsUntil($organization, $range->endUtc)
            ->where('orders.paid_at', '>=', $range->startUtc);
    }

    private function validProductItemsUntil(Organization $organization, CarbonImmutable $endUtc): Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.organization_id', $organization->id)
            ->where('orders.payment_status', PaymentStatus::Paid->value)
            ->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '<=', $endUtc)
            ->whereNull('order_items.parent_item_id')
            ->where('order_items.item_type', ItemType::Product->value)
            ->where('order_items.item_status', '!=', ItemStatus::Cancelled->value);
    }

    /**
     * @return array<string, int>
     */
    private function transactionCountByType(Builder $paidOrders): array
    {
        $rows = $paidOrders
            ->selectRaw('orders.order_type, COUNT(*) as total')
            ->groupBy('orders.order_type')
            ->pluck('total', 'order_type');

        $counts = [];

        foreach (OrderType::cases() as $case) {
            $counts[$case->value] = (int) ($rows[$case->value] ?? 0);
        }

        foreach ($rows as $type => $total) {
            if (! array_key_exists((string) $type, $counts)) {
                $counts[(string) $type] = (int) $total;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, array{count: int, amount: int}>
     */
    private function paymentMethodBreakdown(Builder $paidOrders): array
    {
        $rows = $paidOrders
            ->selectRaw(
                "COALESCE(NULLIF(orders.payment_method, ''), 'unknown') as method,
                COUNT(*) as total,
                COALESCE(SUM(orders.total_amount), 0) as amount"
            )
            ->groupByRaw("COALESCE(NULLIF(orders.payment_method, ''), 'unknown')")
            ->orderBy('method')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[(string) $row->method] = [
                'count' => (int) $row->total,
                'amount' => $this->money($row->amount),
            ];
        }

        return $breakdown;
    }

    /**
     * @return array{count: int, total_amount: int}
     */
    private function cancelledSummary(Organization $organization, ReportDateRange $range): array
    {
        $row = $this->cancelledOrders($organization, $range)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        return [
            'count' => (int) ($row->total ?? 0),
            'total_amount' => $this->money($row->total_amount ?? 0),
        ];
    }

    /**
     * @return array<int, array{date: string, revenue: int, transactions: int}>
     */
    private function financialBreakdown(Builder $paidOrders, ReportDateRange $range, string $groupBy): array
    {
        [$periodExpression, $bindings] = $this->localPeriodExpression('orders.paid_at', $groupBy, $range);

        $rows = $paidOrders
            ->selectRaw(
                "{$periodExpression} as period,
                COUNT(*) as transactions,
                COALESCE(SUM(orders.total_amount), 0) as revenue",
                $bindings,
            )
            ->groupByRaw('1')
            ->orderBy('period')
            ->get()
            ->keyBy(fn (object $row): string => $this->dateKey($row->period));

        return collect($range->periodKeys($groupBy))
            ->map(function (string $period) use ($rows): array {
                $row = $rows->get($period);

                return [
                    'date' => $period,
                    'revenue' => $row !== null ? $this->money($row->revenue) : 0,
                    'transactions' => $row !== null ? (int) $row->transactions : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function localPeriodExpression(string $column, string $groupBy, ReportDateRange $range): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $unit = match ($groupBy) {
                'weekly' => 'week',
                'monthly' => 'month',
                default => 'day',
            };

            return [
                "date_trunc('{$unit}', timezone(?, timezone('UTC', {$column})))::date",
                [$range->timezone],
            ];
        }

        if ($driver === 'sqlite') {
            return [
                match ($groupBy) {
                    'weekly' => "date({$column}, '-' || ((strftime('%w', {$column}) + 6) % 7) || ' days')",
                    'monthly' => "date({$column}, 'start of month')",
                    default => "date({$column})",
                },
                [],
            ];
        }

        return ["date({$column})", []];
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function localHourExpression(string $column, ReportDateRange $range): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return [
                "EXTRACT(HOUR FROM timezone(?, timezone('UTC', {$column})))::int",
                [$range->timezone],
            ];
        }

        if ($driver === 'sqlite') {
            return ["CAST(strftime('%H', {$column}) AS INTEGER)", []];
        }

        return ["EXTRACT(HOUR FROM {$column})", []];
    }

    private function dateKey(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value)->toDateString();
    }

    private function money(mixed $value): int
    {
        return (int) round((float) ($value ?? 0));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function meta(ReportDateRange $range, array $extra = []): array
    {
        return array_merge([
            'timezone' => $range->timezone,
            'start_date' => $range->startLocal->toDateString(),
            'end_date' => $range->endLocal->toDateString(),
        ], $extra);
    }
}
