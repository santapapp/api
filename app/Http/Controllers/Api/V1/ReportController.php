<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FinancialSummaryReportRequest;
use App\Http\Requests\Reports\ProductBestsellersReportRequest;
use App\Http\Requests\Reports\ProductTrendReportRequest;
use App\Http\Requests\Reports\ReportDateRangeRequest;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * Reports for owner dashboard in the active organization (`X-Org-ID`).
 *
 * All revenue reports use `paid_at` in the organization timezone and include
 * only orders whose payment status is `paid`. Cancelled summaries use
 * `cancelled_at` because cancelled orders may never have a paid timestamp.
 *
 * @tags Mobile Reports
 */
final class ReportController extends Controller
{
    /**
     * Financial summary grouped daily, weekly, or monthly.
     *
     * Returns paid revenue totals, order type counts, payment method breakdown,
     * cancelled transaction totals, and zero-filled period breakdown. Monetary
     * values are integer Rupiah from the canonical order snapshot fields:
     * `subtotal_amount`, `discount_amount`, `tax_amount`,
     * `service_charge_amount`, and `total_amount`.
     */
    public function financialSummary(
        FinancialSummaryReportRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->financialSummary(
                $request->organization(),
                $request->dateRange(),
                $request->groupBy(),
            ),
        ]);
    }

    /**
     * Bestselling products by valid paid order items.
     *
     * Product revenue uses root `order_items.subtotal`, which already contains
     * the transaction snapshot of base price plus selected variant/addon deltas.
     * Cancelled root items are excluded.
     */
    public function productBestsellers(
        ProductBestsellersReportRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->productBestsellers(
                $request->organization(),
                $request->dateRange(),
                $request->limit(),
            ),
        ]);
    }

    /**
     * Available products with no valid paid sales in the requested period.
     *
     * `last_sold_date` is computed from the last paid order item up to
     * `end_date`, not only from the current report range.
     */
    public function productsNoSales(
        ReportDateRangeRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->productsNoSales(
                $request->organization(),
                $request->dateRange(),
            ),
        ]);
    }

    /**
     * Product sales grouped by category.
     *
     * The current Santap schema has no menu category relation after the
     * restructure migration, so all sales are intentionally grouped into an
     * `Uncategorized` bucket.
     */
    public function productSalesByCategory(
        ReportDateRangeRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->productSalesByCategory(
                $request->organization(),
                $request->dateRange(),
            ),
        ]);
    }

    /**
     * Daily trend for one product.
     *
     * Product lookup is scoped to the active organization so products from
     * other organizations are returned as not found.
     */
    public function productTrend(
        ProductTrendReportRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->productTrend(
                $request->organization(),
                $request->dateRange(),
                $request->productId(),
            ),
        ]);
    }

    /**
     * Operational performance by cashier.
     *
     * The current `orders` table has no `paid_by` or `closed_by` column, so
     * this report uses `orders.created_by`. Paid self-service rows without a
     * creator are grouped as `Unassigned`.
     */
    public function operationalByCashier(
        ReportDateRangeRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->operationalByCashier(
                $request->organization(),
                $request->dateRange(),
            ),
        ]);
    }

    /**
     * Paid transaction peak hours in the organization timezone.
     *
     * The PostgreSQL implementation uses `EXTRACT(HOUR FROM timezone(...))`
     * and returns all hours `0` through `23`, including zero-value hours.
     */
    public function operationalPeakHours(
        ReportDateRangeRequest $request,
        ReportService $reports,
    ): JsonResponse {
        return response()->json([
            'data' => $reports->operationalPeakHours(
                $request->organization(),
                $request->dateRange(),
            ),
        ]);
    }
}
