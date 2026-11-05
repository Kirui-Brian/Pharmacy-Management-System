<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductBatch;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckExpiriesAndStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pms:check-expiries';

    /**
     * The console command description.
     */
    protected $description = 'Scan batches for expiration, auto-quarantine/expire past dates, and alert on low stock levels';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting automated stock & expiry check...');

        $today = Carbon::today();
        $criticalThreshold = Carbon::today()->addDays(30);

        // 1. Auto-transition past batches to 'expired'
        $expiredBatches = ProductBatch::where('status', '!=', 'expired')
            ->where('expiry_date', '<', $today->toDateString())
            ->get();

        $expiredCount = 0;
        foreach ($expiredBatches as $batch) {
            $oldStatus = $batch->status;
            $batch->update(['status' => 'expired']);
            $expiredCount++;

            Log::warning("Batch #{$batch->batch_number} for product ID {$batch->product_id} expired on {$batch->expiry_date} and has been marked 'expired'.");

            // Create system audit entry if AuditLog table exists
            if (class_exists(AuditLog::class)) {
                try {
                    AuditLog::create([
                        'user_id' => null,
                        'event' => 'batch_expired_cron',
                        'auditable_type' => ProductBatch::class,
                        'auditable_id' => $batch->id,
                        'old_values' => ['status' => $oldStatus],
                        'new_values' => ['status' => 'expired'],
                        'url' => 'console/pms:check-expiries',
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Artisan Scheduler',
                    ]);
                } catch (\Throwable) {
                    // Ignore audit log persistence issues
                }
            }
        }

        // 2. Identify critical batches (expiring in <= 30 days)
        $criticalBatches = ProductBatch::where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->whereBetween('expiry_date', [$today->toDateString(), $criticalThreshold->toDateString()])
            ->with('product')
            ->get();

        // 3. Identify products below reorder level
        $products = Product::where('is_active', true)->with(['batches' => function ($q) {
            $q->where('status', 'active')->where('quantity_remaining', '>', 0);
        }])->get();

        $lowStockProducts = [];
        foreach ($products as $p) {
            $currentStock = $p->batches->sum('quantity_remaining');
            if ($currentStock <= $p->reorder_level) {
                $lowStockProducts[] = [
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'current_stock' => $currentStock,
                    'reorder_level' => $p->reorder_level,
                ];
            }
        }

        // Output summary table
        $this->table(
            ['Metric', 'Count', 'Status'],
            [
                ['Batches Auto-Marked Expired', $expiredCount, $expiredCount > 0 ? '<comment>Action Taken</comment>' : '<info>Clean</info>'],
                ['Critical Batches (<= 30 Days)', $criticalBatches->count(), $criticalBatches->count() > 0 ? '<comment>Warning</comment>' : '<info>Normal</info>'],
                ['Products Below Reorder Level', count($lowStockProducts), count($lowStockProducts) > 0 ? '<comment>Restock Required</comment>' : '<info>Adequate</info>'],
            ]
        );

        $this->info("Stock & Expiry check completed successfully at " . Carbon::now()->toIso8601String());
        return 0;
    }
}
