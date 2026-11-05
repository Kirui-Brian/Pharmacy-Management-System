<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_purchase_price', 10, 2);
            $table->decimal('unit_selling_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2); // (quantity * unit_selling_price) - discount_amount
            $table->decimal('total_cogs', 12, 2); // quantity * unit_purchase_price
            $table->decimal('gross_profit', 12, 2); // subtotal - total_cogs
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
            $table->index(['product_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
