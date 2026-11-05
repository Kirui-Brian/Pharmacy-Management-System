<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Services\AnalyticsReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected AnalyticsReportService $analyticsService;

    public function __construct(AnalyticsReportService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function getDashboardSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id') ? (int) $request->query('branch_id') : null;

        $summary = $this->analyticsService->getDashboardSummary($user->pharmacy, $branchId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function getSalesAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $analytics = $this->analyticsService->getSalesAnalytics(
            $user->pharmacy,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    public function getTopSellingProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 10);
        $topProducts = $this->analyticsService->getTopSellingProducts(
            $user->pharmacy,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('branch_id') ? (int) $request->query('branch_id') : null,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => $topProducts,
        ]);
    }

    public function getInventoryValuation(Request $request): JsonResponse
    {
        $user = $request->user();
        $valuation = $this->analyticsService->getInventoryValuationReport(
            $user->pharmacy,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $valuation,
        ]);
    }

    public function getExpiryRiskAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();
        $analysis = $this->analyticsService->getExpiryRiskAnalysis(
            $user->pharmacy,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    public function getDeadStock(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->query('days', 60);
        $deadStock = $this->analyticsService->getDeadStock(
            $user->pharmacy,
            $days,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $deadStock,
        ]);
    }

    public function getClinicalDispensing(Request $request): JsonResponse
    {
        $user = $request->user();
        $clinical = $this->analyticsService->getClinicalDispensingAnalytics(
            $user->pharmacy,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $clinical,
        ]);
    }

    /**
     * Export Sales to CSV.
     */
    public function exportSalesCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $start = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $end = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : Carbon::now()->endOfDay();

        $sales = Sale::where('pharmacy_id', $user->pharmacy_id)
            ->whereBetween('sale_date', [$start, $end])
            ->with(['payments', 'cashier', 'customer'])
            ->orderBy('sale_date', 'desc')
            ->get();

        $filename = "sales_report_" . Carbon::now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($sales) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Receipt Number',
                'Date & Time',
                'Subtotal (KES)',
                'Discount (KES)',
                'Tax (KES)',
                'Total Amount (KES)',
                'Payment Method',
                'Cashier',
                'Customer',
                'Status',
            ]);

            foreach ($sales as $s) {
                $methods = $s->payments->pluck('payment_method')->unique()->implode(', ') ?: 'Cash';
                fputcsv($handle, [
                    $s->receipt_number,
                    Carbon::parse($s->sale_date)->format('Y/m/d h:i:s a'),
                    number_format($s->subtotal, 2, '.', ''),
                    number_format($s->discount_amount, 2, '.', ''),
                    number_format($s->tax_amount, 2, '.', ''),
                    number_format($s->total_amount, 2, '.', ''),
                    strtoupper($methods),
                    $s->cashier?->name ?? 'System',
                    $s->customer?->name ?? 'Walk-in',
                    ucfirst($s->payment_status),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export Inventory Valuation to CSV.
     */
    public function exportInventoryCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $batches = ProductBatch::whereHas('product', fn($q) => $q->where('pharmacy_id', $user->pharmacy_id))
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->with(['product.category', 'supplier'])
            ->get();

        $filename = "inventory_valuation_" . Carbon::now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($batches) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Product Name',
                'SKU',
                'Category',
                'Batch Number',
                'Qty Remaining',
                'Unit Purchase Price (KES)',
                'Unit Retail Price (KES)',
                'Total Valuation Cost (KES)',
                'Total Valuation Retail (KES)',
                'Potential Gross Profit (KES)',
                'Expiry Date',
                'Supplier',
            ]);

            foreach ($batches as $b) {
                $qty = (float) $b->quantity_remaining;
                $cost = (float) $b->purchase_price_per_unit;
                $sell = (float) $b->selling_price_per_unit;
                $valCost = round($qty * $cost, 2);
                $valSell = round($qty * $sell, 2);

                fputcsv($handle, [
                    $b->product?->name ?? 'Unknown',
                    $b->product?->sku ?? '—',
                    $b->product?->category?->name ?? 'General',
                    $b->batch_number,
                    $qty,
                    number_format($cost, 2, '.', ''),
                    number_format($sell, 2, '.', ''),
                    number_format($valCost, 2, '.', ''),
                    number_format($valSell, 2, '.', ''),
                    number_format($valSell - $valCost, 2, '.', ''),
                    $b->expiry_date,
                    $b->supplier?->name ?? 'Standard',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Export Expiry Watchlist to CSV.
     */
    public function exportExpiryCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $batches = ProductBatch::whereHas('product', fn($q) => $q->where('pharmacy_id', $user->pharmacy_id))
            ->whereIn('status', ['active', 'quarantined', 'expired'])
            ->where('quantity_remaining', '>', 0)
            ->with('product.category')
            ->orderBy('expiry_date', 'asc')
            ->get();

        $today = Carbon::today();
        $filename = "expiry_watchlist_" . Carbon::now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($batches, $today) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Product Name',
                'SKU',
                'Batch Number',
                'Quantity Remaining',
                'Expiry Date',
                'Days Remaining',
                'Risk Category',
                'Unit Purchase Cost (KES)',
                'Total Value-at-Risk (KES)',
            ]);

            foreach ($batches as $b) {
                $exp = Carbon::parse($b->expiry_date);
                $days = (int) $today->diffInDays($exp, false);
                $qty = (float) $b->quantity_remaining;
                $cost = (float) $b->purchase_price_per_unit;
                $val = round($qty * $cost, 2);

                $category = 'Safe (> 90 Days)';
                if ($exp->isPast()) {
                    $category = 'EXPIRED (BLOCKED)';
                } elseif ($days <= 30) {
                    $category = 'Critical (<= 30 Days)';
                } elseif ($days <= 60) {
                    $category = 'Warning (31 - 60 Days)';
                } elseif ($days <= 90) {
                    $category = 'Caution (61 - 90 Days)';
                }

                fputcsv($handle, [
                    $b->product?->name ?? 'Unknown',
                    $b->product?->sku ?? '—',
                    $b->batch_number,
                    $qty,
                    $b->expiry_date,
                    $days,
                    $category,
                    number_format($cost, 2, '.', ''),
                    number_format($val, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
