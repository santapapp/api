<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\OpenBill;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get date range filter relative to organization timezone.
     */
    private function getDateRange(Request $request, string $tz): array
    {
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $startDate = $startDateInput 
            ? Carbon::parse($startDateInput, $tz)->startOfDay() 
            : Carbon::now($tz)->subDays(30)->startOfDay();
        
        $endDate = $endDateInput 
            ? Carbon::parse($endDateInput, $tz)->endOfDay() 
            : Carbon::now($tz)->endOfDay();

        return [
            $startDate->setTimezone('UTC'),
            $endDate->setTimezone('UTC')
        ];
    }

    /**
     * Get basic sales metrics summary.
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $organization = app(\App\Services\OrganizationContext::class)->get();
        $tz = $organization->timezone ?: 'Asia/Jakarta';
        [$startUtc, $endUtc] = $this->getDateRange($request, $tz);

        $totalSales = Payment::where('organization_id', $organization->id)
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->sum('amount');

        $totalOrders = Order::where('organization_id', $organization->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();

        $totalBills = OpenBill::where('organization_id', $organization->id)
            ->where('status', BillStatus::Closed)
            ->whereBetween('closed_at', [$startUtc, $endUtc])
            ->count();

        $averageBillAmount = OpenBill::where('organization_id', $organization->id)
            ->where('status', BillStatus::Closed)
            ->whereBetween('closed_at', [$startUtc, $endUtc])
            ->avg('total_amount') ?? 0.00;

        return response()->json([
            'data' => [
                'total_sales' => (float) $totalSales,
                'total_orders' => $totalOrders,
                'total_bills' => $totalBills,
                'average_bill_amount' => (float) $averageBillAmount,
                'start_date' => $startUtc->setTimezone($tz)->toIso8601String(),
                'end_date' => $endUtc->setTimezone($tz)->toIso8601String(),
            ]
        ]);
    }

    public function dailySales(Request $request): JsonResponse
    {
        $organization = app(\App\Services\OrganizationContext::class)->get();
        $tz = $organization->timezone ?: 'Asia/Jakarta';
        [$startUtc, $endUtc] = $this->getDateRange($request, $tz);

        $isSqlite = DB::getDriverName() === 'sqlite';
        $dateExpr = $isSqlite 
            ? "date(paid_at)" 
            : "DATE(paid_at AT TIME ZONE 'UTC' AT TIME ZONE '$tz')";

        $dailySales = Payment::where('organization_id', $organization->id)
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->select([
                DB::raw("$dateExpr as date"),
                DB::raw("SUM(amount) as total_amount"),
                DB::raw("COUNT(*) as payment_count")
            ])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'data' => $dailySales->map(fn($item) => [
                'date' => $item->date,
                'total_amount' => (float) $item->total_amount,
                'payment_count' => (int) $item->payment_count,
            ])
        ]);
    }

    /**
     * Get top selling menu items.
     */
    public function menuSales(Request $request): JsonResponse
    {
        $organization = app(\App\Services\OrganizationContext::class)->get();
        $tz = $organization->timezone ?: 'Asia/Jakarta';
        [$startUtc, $endUtc] = $this->getDateRange($request, $tz);

        $menuSales = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.organization_id', $organization->id)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.created_at', [$startUtc, $endUtc])
            ->select([
                'order_items.menu_id',
                'order_items.menu_name_snapshot as menu_name',
                DB::raw("SUM(order_items.quantity) as quantity_sold"),
                DB::raw("SUM(order_items.subtotal_amount) as total_revenue")
            ])
            ->groupBy('order_items.menu_id', 'order_items.menu_name_snapshot')
            ->orderBy('quantity_sold', 'desc')
            ->limit((int) $request->input('limit', 10))
            ->get();

        return response()->json([
            'data' => $menuSales->map(fn($item) => [
                'menu_id' => $item->menu_id,
                'menu_name' => $item->menu_name,
                'quantity_sold' => (int) $item->quantity_sold,
                'total_revenue' => (float) $item->total_revenue,
            ])
        ]);
    }

    /**
     * Get performance split by payment methods.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $organization = app(\App\Services\OrganizationContext::class)->get();
        $tz = $organization->timezone ?: 'Asia/Jakarta';
        [$startUtc, $endUtc] = $this->getDateRange($request, $tz);

        $paymentMethods = Payment::where('organization_id', $organization->id)
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$startUtc, $endUtc])
            ->select([
                'method as payment_method',
                DB::raw("SUM(amount) as total_amount"),
                DB::raw("COUNT(*) as payment_count")
            ])
            ->groupBy('method')
            ->orderBy('total_amount', 'desc')
            ->get();

        return response()->json([
            'data' => $paymentMethods->map(fn($item) => [
                'payment_method' => $item->payment_method,
                'total_amount' => (float) $item->total_amount,
                'payment_count' => (int) $item->payment_count,
            ])
        ]);
    }
}
