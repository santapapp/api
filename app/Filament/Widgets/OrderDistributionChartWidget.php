<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderDistributionChartWidget extends ChartWidget
{
    protected ?string $heading = 'Distribusi Metode Pembayaran';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $methods = Order::query()
            ->select('payment_method', DB::raw('count(*) as total'))
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->get();

        $labels = [];
        $data = [];
        $colors = [];

        $colorPalette = [
            'qris' => '#3b82f6', // Blue 500
            'cash' => '#10b981', // Emerald 500
            'transfer' => '#8b5cf6', // Violet 500
            'edc' => '#f59e0b', // Amber 500
        ];

        foreach ($methods as $row) {
            $method = strtolower($row->payment_method);
            $labels[] = strtoupper($method);
            $data[] = $row->total;
            $colors[] = $colorPalette[$method] ?? '#64748b'; // Slate 500 as fallback
        }

        if (empty($data)) {
            $labels = ['Belum ada data'];
            $data = [1];
            $colors = ['#e2e8f0']; // Slate 200
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Transaksi',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'cutout' => '70%',
        ];
    }
}
