<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Cashier
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->index();
            $table->string('receipt_number')->index();
            $table->dateTime('sale_date')->index();
            $table->decimal('subtotal', 14, 2);
            $table->enum('discount_type', ['none', 'percentage', 'fixed'])->default('none');
            $table->decimal('discount_rate', 5, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->decimal('balance_due', 14, 2)->default(0.00);
            $table->decimal('cost_of_goods_sold', 14, 2)->default(0.00); // Strict COGS
            $table->decimal('gross_profit', 14, 2)->default(0.00); // total_amount - COGS
            $table->enum('payment_status', ['paid', 'partial', 'credit', 'refunded'])->default('paid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'receipt_number']);
            $table->unique(['pharmacy_id', 'invoice_number']);
            $table->index(['pharmacy_id', 'branch_id', 'sale_date']);
            $table->index(['pharmacy_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
