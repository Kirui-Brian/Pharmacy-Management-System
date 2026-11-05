<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Staff processing return
            $table->string('return_number')->index();
            $table->decimal('total_refund_amount', 14, 2);
            $table->string('reason');
            $table->enum('refund_method', ['cash', 'mpesa', 'credit_balance', 'none'])->default('cash');
            $table->enum('status', ['completed', 'pending_approval'])->default('completed');
            $table->timestamps();

            $table->unique(['pharmacy_id', 'return_number']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_refund_price', 10, 2);
            $table->decimal('subtotal_refund', 12, 2);
            $table->enum('condition', ['resellable', 'damaged', 'expired'])->default('resellable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
