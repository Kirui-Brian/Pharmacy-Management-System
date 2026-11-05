<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('movement_type', [
                'purchase_receipt',
                'pos_sale',
                'dispense_out',
                'sale_return_restock',
                'purchase_return',
                'adjustment_addition',
                'adjustment_subtraction',
                'damaged_scrap',
                'expired_scrap',
                'transfer_in',
                'transfer_out',
            ])->index();
            $table->decimal('quantity_delta', 14, 4); // positive or negative
            $table->decimal('balance_after', 14, 4); // snapshot of batch balance after movement
            $table->decimal('unit_cost', 14, 4); // batch purchase cost
            $table->decimal('total_cost', 14, 4); // quantity_delta * unit_cost
            $table->string('reference_type', 100)->nullable(); // e.g. App\Models\Sale, App\Models\StockAdjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['branch_id', 'movement_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
