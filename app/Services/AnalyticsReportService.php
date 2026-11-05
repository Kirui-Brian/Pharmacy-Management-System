<?php

namespace App\Services;

use App\Models\Dispensation;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsReportService
{
    /**
     * Get high-level executive dashboard summary.
     */
    public function getDashboardSummary(Pharmacy $pharmacy, ?int $branchId = null): array
    {
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfDay();

        // 1. Sales metrics
        $salesTodayQuery = Sale::where('pharmacy_id', $pharmacy->id)->whereBetween('sale_date', [$todayStart, $todayEnd]);
        if ($branchId) $salesTodayQuery->where('branch_id', $branchId);

        $salesMonthQuery = Sale::where('pharmacy_id', $pharmacy->id)->whereBetween('sale_date', [$monthStart, $monthEnd]);
        if ($branchId) $salesMonthQuery->where('branch_id', $branchId);

        $todayRevenue = (float) $salesTodayQuery->sum('total_amount');
        $todaySalesCount = $salesTodayQuery->count();

        $monthRevenue = (float) $salesMonthQuery->sum('total_amount');
        $monthCogs = (float) $salesMonthQuery->sum('cost_of_goods_sold');
        $monthGrossProfit = round($monthRevenue - $monthCogs, 2);
        $monthSalesCount = $salesMonthQuery->count();

        // 2. Inventory valuation
        $batchQuery = ProductBatch::whereHas('product', function ($q) use ($pharmacy) {
            $q->where('pharmacy_id', $pharmacy->id);
        })->where('status', 'active');
        if ($branchId) $batchQuery->where('branch_id', $branchId);

        $inventoryValuationCost = (float) $batchQuery->sum(DB::raw('quantity_remaining * purchase_price_per_unit'));
        $inventoryValuationRetail = (float) $batchQuery->sum(DB::raw('quantity_remaining * selling_price_per_unit'));
        $potentialGrossProfit = round($inventoryValuationRetail - $inventoryValuationCost, 2);

        // 3. Expiry risk exposure
        $now = Carbon::now();
        $day30 = Carbon::now()->addDays(30);

        $criticalExpiryVal = (float) ProductBatch::whereHas('product', fn($q) => $q->where('pharmacy_id', $pharmacy->id))
            ->where('status', 'active')
            ->where('expiry_date', '<=', $day30)
            ->sum(DB::raw('quantity_remaining * purchase_price_per_unit'));

        // 4. Accounts payable
        $apQuery = SupplierInvoice::where('pharmacy_id', $pharmacy->id)
            ->whereIn('payment_status', ['unpaid', 'partial', 'overdue']);
        if ($branchId) $apQuery->where('branch_id', $branchId);
        $totalPayable = (float) $apQuery->sum('balance_due');

        return [
            'today' => [
                'revenue' => round($todayRevenue, 2),
                'transactions' => $todaySalesCount,
            ],
            'month_to_date' => [
                'revenue' => round($monthRevenue, 2),
                'cogs' => round($monthCogs, 2),
                'gross_profit' => $monthGrossProfit,
                'gross_margin' => $monthRevenue > 0 ? round(($monthGrossProfit / $monthRevenue) * 100, 2) : 0.00,
                'transactions' => $monthSalesCount,
                'average_basket_size' => $monthSalesCount > 0 ? round($monthRevenue / $monthSalesCount, 2) : 0.00,
            ],
            'inventory' => [
                'valuation_at_cost' => round($inventoryValuationCost, 2),
                'valuation_at_retail' => round($inventoryValuationRetail, 2),
                'potential_gross_profit' => $potentialGrossProfit,
                'critical_expiry_risk' => round($criticalExpiryVal, 2),
            ],
            'accounts_payable' => round($totalPayable, 2),
        ];
    }

    /**
     * Get periodic sales analytics with daily trend breakdown.
     */
    public function getSalesAnalytics(Pharmacy $pharmacy, ?string $startDate = null, ?string $endDate = null, ?int $branchId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $query = Sale::where('pharmacy_id', $pharmacy->id)->whereBetween('sale_date', [$start, $end]);
        if ($branchId) $query->where('branch_id', $branchId);

        $sales = $query->with('payments')->orderBy('sale_date', 'asc')->get();

        $totalRevenue = 0.00;
        $totalCogs = 0.00;
        $dailyTrends = [];
        $paymentMethods = [
            'cash' => 0.00,
            'mpesa' => 0.00,
            'card' => 0.00,
            'credit' => 0.00,
        ];

        foreach ($sales as $sale) {
            $rev = (float) $sale->total_amount;
            $cogs = (float) $sale->cost_of_goods_sold;
            $date = Carbon::parse($sale->sale_date)->format('Y/m/d');

            $totalRevenue += $rev;
            $totalCogs += $cogs;

            if (!isset($dailyTrends[$date])) {
                $dailyTrends[$date] = [
                    'date' => $date,
                    'revenue' => 0.00,
                    'cogs' => 0.00,
                    'gross_profit' => 0.00,
                    'transactions' => 0,
                ];
            }

            $dailyTrends[$date]['revenue'] += $rev;
            $dailyTrends[$date]['cogs'] += $cogs;
            $dailyTrends[$date]['gross_profit'] += ($rev - $cogs);
            $dailyTrends[$date]['transactions'] += 1;

            foreach ($sale->payments as $p) {
                $m = $p->payment_method;
                if (isset($paymentMethods[$m])) {
                    $paymentMethods[$m] += (float) $p->amount;
                } else {
                    $paymentMethods[$m] = (float) $p->amount;
                }
            }
        }

        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $totalTransactions = $sales->count();

        return [
            'period' => [
                'start_date' => $start->format('Y/m/d'),
                'end_date' => $end->format('Y/m/d'),
            ],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_cogs' => round($totalCogs, 2),
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0.00,
                'total_transactions' => $totalTransactions,
                'average_basket_size' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0.00,
            ],
            'daily_trends' => array_values($dailyTrends),
            'payment_methods' => $paymentMethods,
        ];
    }

    /**
     * Get top fastest-selling medicines by units sold and by revenue.
     */
    public function getTopSellingProducts(Pharmacy $pharmacy, ?string $startDate = null, ?string $endDate = null, ?int $branchId = null, int $limit = 10): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $query = SaleItem::whereHas('sale', function ($q) use ($pharmacy, $start, $end, $branchId) {
            $q->where('pharmacy_id', $pharmacy->id)->whereBetween('sale_date', [$start, $end]);
            if ($branchId) $q->where('branch_id', $branchId);
        })
        ->select(
            'product_id',
            DB::raw('SUM(quantity) as total_units_sold'),
            DB::raw('SUM(subtotal) as total_revenue'),
            DB::raw('SUM(total_cogs) as total_cogs'),
            DB::raw('SUM(gross_profit) as total_profit')
        )
        ->groupBy('product_id')
        ->orderByDesc('total_units_sold')
        ->limit($limit)
        ->with('product.category');

        $items = $query->get();

        return $items->map(function ($item) {
            $rev = (float) $item->total_revenue;
            $profit = (float) $item->total_profit;
            return [
                'product_id' => $item->product_id,
                'name' => $item->product?->name ?? 'Unknown',
                'sku' => $item->product?->sku ?? '—',
                'category' => $item->product?->category?->name ?? 'General',
                'units_sold' => (float) $item->total_units_sold,
                'revenue' => round($rev, 2),
                'gross_profit' => round($profit, 2),
                'margin_percent' => $rev > 0 ? round(($profit / $rev) * 100, 2) : 0.00,
            ];
        })->toArray();
    }

    /**
     * Get dead stock / stagnant inventory with 0 sales over given days.
     */
    public function getDeadStock(Pharmacy $pharmacy, int $daysInactive = 60, ?int $branchId = null): array
    {
        $cutoffDate = Carbon::now()->subDays($daysInactive);

        // Subquery of product IDs sold recently
        $soldProductIds = SaleItem::whereHas('sale', function ($q) use ($pharmacy, $cutoffDate, $branchId) {
            $q->where('pharmacy_id', $pharmacy->id)->where('sale_date', '>=', $cutoffDate);
            if ($branchId) $q->where('branch_id', $branchId);
        })->pluck('product_id')->unique();

        // Active batches for products that have NOT been sold in cutoff period
        $deadBatchesQuery = ProductBatch::whereHas('product', function ($q) use ($pharmacy, $soldProductIds) {
            $q->where('pharmacy_id', $pharmacy->id)->whereNotIn('id', $soldProductIds);
        })
        ->where('status', 'active')
        ->where('quantity_remaining', '>', 0)
        ->with(['product.category', 'supplier']);

        if ($branchId) $deadBatchesQuery->where('branch_id', $branchId);

        $batches = $deadBatchesQuery->get();

        $deadStockItems = [];
        $totalCapitalTiedUp = 0.00;

        foreach ($batches as $b) {
            $qty = (float) $b->quantity_remaining;
            $unitCost = (float) $b->purchase_price_per_unit;
            $tiedCapital = round($qty * $unitCost, 2);
            $totalCapitalTiedUp += $tiedCapital;

            $deadStockItems[] = [
                'batch_id' => $b->id,
                'batch_number' => $b->batch_number,
                'product_name' => $b->product?->name ?? 'Unknown',
                'sku' => $b->product?->sku ?? '—',
                'category' => $b->product?->category?->name ?? 'General',
                'supplier' => $b->supplier?->name ?? 'Standard',
                'quantity_remaining' => $qty,
                'unit_cost' => $unitCost,
                'tied_up_capital' => $tiedCapital,
                'expiry_date' => $b->expiry_date,
            ];
        }

        return [
            'days_inactive' => $daysInactive,
            'total_items_count' => count($deadStockItems),
            'total_tied_up_capital' => round($totalCapitalTiedUp, 2),
            'items' => $deadStockItems,
        ];
    }

    /**
     * Get comprehensive Inventory Valuation Report.
     */
    public function getInventoryValuationReport(Pharmacy $pharmacy, ?int $branchId = null): array
    {
        $batchQuery = ProductBatch::whereHas('product', function ($q) use ($pharmacy) {
            $q->where('pharmacy_id', $pharmacy->id);
        })
        ->where('status', 'active')
        ->where('quantity_remaining', '>', 0)
        ->with(['product.category']);

        if ($branchId) $batchQuery->where('branch_id', $branchId);

        $batches = $batchQuery->get();

        $totalValuationCost = 0.00;
        $totalValuationRetail = 0.00;
        $byCategory = [];
        $items = [];

        foreach ($batches as $b) {
            $qty = (float) $b->quantity_remaining;
            $unitCost = (float) $b->purchase_price_per_unit;
            $unitSell = (float) $b->selling_price_per_unit;

            $costVal = round($qty * $unitCost, 2);
            $retailVal = round($qty * $unitSell, 2);
            $potentialProfit = round($retailVal - $costVal, 2);

            $totalValuationCost += $costVal;
            $totalValuationRetail += $retailVal;

            $catName = $b->product?->category?->name ?? 'Uncategorized';
            if (!isset($byCategory[$catName])) {
                $byCategory[$catName] = [
                    'category' => $catName,
                    'cost_value' => 0.00,
                    'retail_value' => 0.00,
                    'batches_count' => 0,
                ];
            }
            $byCategory[$catName]['cost_value'] += $costVal;
            $byCategory[$catName]['retail_value'] += $retailVal;
            $byCategory[$catName]['batches_count'] += 1;

            $items[] = [
                'batch_id' => $b->id,
                'batch_number' => $b->batch_number,
                'product_name' => $b->product?->name ?? 'Unknown',
                'sku' => $b->product?->sku ?? '—',
                'category' => $catName,
                'quantity_remaining' => $qty,
                'unit_cost' => $unitCost,
                'unit_sell' => $unitSell,
                'valuation_cost' => $costVal,
                'valuation_retail' => $retailVal,
                'potential_gross_profit' => $potentialProfit,
                'expiry_date' => $b->expiry_date,
            ];
        }

        $potentialMarkup = round($totalValuationRetail - $totalValuationCost, 2);

        return [
            'summary' => [
                'total_valuation_cost' => round($totalValuationCost, 2),
                'total_valuation_retail' => round($totalValuationRetail, 2),
                'potential_gross_profit' => $potentialMarkup,
                'overall_markup_percent' => $totalValuationCost > 0 ? round(($potentialMarkup / $totalValuationCost) * 100, 2) : 0.00,
                'total_batches_count' => count($items),
            ],
            'by_category' => array_values($byCategory),
            'items' => $items,
        ];
    }

    /**
     * Get Expiry Risk & Value-at-Risk Analysis across 30/60/90 days buckets.
     */
    public function getExpiryRiskAnalysis(Pharmacy $pharmacy, ?int $branchId = null): array
    {
        $batchQuery = ProductBatch::whereHas('product', function ($q) use ($pharmacy) {
            $q->where('pharmacy_id', $pharmacy->id);
        })
        ->whereIn('status', ['active', 'quarantined', 'expired'])
        ->where('quantity_remaining', '>', 0)
        ->with('product.category');

        if ($branchId) $batchQuery->where('branch_id', $branchId);

        $batches = $batchQuery->get();

        $today = Carbon::today();
        $d30 = Carbon::today()->addDays(30);
        $d60 = Carbon::today()->addDays(60);
        $d90 = Carbon::today()->addDays(90);

        $expired = ['count' => 0, 'units' => 0, 'cost_value' => 0.00, 'items' => []];
        $critical30 = ['count' => 0, 'units' => 0, 'cost_value' => 0.00, 'items' => []];
        $warning60 = ['count' => 0, 'units' => 0, 'cost_value' => 0.00, 'items' => []];
        $caution90 = ['count' => 0, 'units' => 0, 'cost_value' => 0.00, 'items' => []];
        $safeStock = ['count' => 0, 'units' => 0, 'cost_value' => 0.00];

        foreach ($batches as $b) {
            $exp = Carbon::parse($b->expiry_date);
            $qty = (float) $b->quantity_remaining;
            $val = round($qty * (float) $b->purchase_price_per_unit, 2);

            $item = [
                'batch_id' => $b->id,
                'batch_number' => $b->batch_number,
                'product_name' => $b->product?->name ?? 'Unknown',
                'sku' => $b->product?->sku ?? '—',
                'quantity_remaining' => $qty,
                'expiry_date' => $b->expiry_date,
                'days_left' => (int) $today->diffInDays($exp, false),
                'cost_value' => $val,
            ];

            if ($exp->isPast()) {
                $expired['count']++;
                $expired['units'] += $qty;
                $expired['cost_value'] += $val;
                $expired['items'][] = $item;
            } elseif ($exp->lte($d30)) {
                $critical30['count']++;
                $critical30['units'] += $qty;
                $critical30['cost_value'] += $val;
                $critical30['items'][] = $item;
            } elseif ($exp->lte($d60)) {
                $warning60['count']++;
                $warning60['units'] += $qty;
                $warning60['cost_value'] += $val;
                $warning60['items'][] = $item;
            } elseif ($exp->lte($d90)) {
                $caution90['count']++;
                $caution90['units'] += $qty;
                $caution90['cost_value'] += $val;
                $caution90['items'][] = $item;
            } else {
                $safeStock['count']++;
                $safeStock['units'] += $qty;
                $safeStock['cost_value'] += $val;
            }
        }

        $totalRiskExposure = round($expired['cost_value'] + $critical30['cost_value'] + $warning60['cost_value'] + $caution90['cost_value'], 2);

        return [
            'total_value_at_risk' => $totalRiskExposure,
            'buckets' => [
                'expired' => $expired,
                'critical_30' => $critical30,
                'warning_60' => $warning60,
                'caution_90' => $caution90,
                'safe' => $safeStock,
            ],
        ];
    }

    /**
     * Get Clinical Dispensing analytics & prescriber metrics.
     */
    public function getClinicalDispensingAnalytics(Pharmacy $pharmacy, ?string $startDate = null, ?string $endDate = null, ?int $branchId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $rxQuery = Prescription::where('pharmacy_id', $pharmacy->id)->whereBetween('created_at', [$start, $end]);
        if ($branchId) $rxQuery->where('branch_id', $branchId);

        $prescriptions = $rxQuery->get();

        $totalRx = $prescriptions->count();
        $fullyDispensed = $prescriptions->where('status', 'fully_dispensed')->count();
        $partiallyDispensed = $prescriptions->where('status', 'partially_dispensed')->count();
        $pending = $prescriptions->where('status', 'pending')->count();

        // Top referring doctors / clinics
        $topPrescribers = Prescription::where('pharmacy_id', $pharmacy->id)
            ->whereBetween('created_at', [$start, $end])
            ->select('prescriber_name', 'prescriber_facility', DB::raw('count(*) as count'))
            ->groupBy('prescriber_name', 'prescriber_facility')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // Total units dispensed via dispensations
        $dispQuery = Dispensation::where('pharmacy_id', $pharmacy->id)->whereBetween('dispensation_date', [$start, $end]);
        if ($branchId) $dispQuery->where('branch_id', $branchId);
        $totalDispensationEvents = $dispQuery->count();

        return [
            'summary' => [
                'total_prescriptions' => $totalRx,
                'fully_dispensed' => $fullyDispensed,
                'partially_dispensed' => $partiallyDispensed,
                'pending' => $pending,
                'fulfillment_rate_percent' => $totalRx > 0 ? round(($fullyDispensed / $totalRx) * 100, 2) : 0.00,
                'dispensation_events' => $totalDispensationEvents,
            ],
            'top_prescribers' => $topPrescribers,
        ];
    }
}
