<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Enums\PaymentStatus;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue (7 Hari Terakhir)';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        // Ambil data dari 6 hari lalu sampai hari ini (7 titik data)
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d M');

            // Hitung revenue untuk hari itu
            $revenue = Order::whereDate('created_at', $date->format('Y-m-d'))
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            $data[] = (float) $revenue;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Pendapatan (Rp)',
                    'data' => $data,
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)', // Emerald 500 alpha 20%
                    'borderColor' => '#10b981', // Emerald 500
                    'tension' => 0.4, // Smooth curve
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
