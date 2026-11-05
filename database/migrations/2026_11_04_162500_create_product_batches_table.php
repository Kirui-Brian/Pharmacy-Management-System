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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->index(); // Index for FEFO fast query
            $table->decimal('quantity_received', 14, 4);
            $table->decimal('quantity_remaining', 14, 4)->default(0.0000)->index();
            $table->decimal('purchase_price_per_unit', 14, 4); // Historic cost basis for COGS
            $table->decimal('selling_price_per_unit', 12, 2);
            $table->enum('status', ['active', 'quarantined', 'expired', 'depleted'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id', 'batch_number'], 'branch_product_batch_unique');
            $table->index(['branch_id', 'status', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
