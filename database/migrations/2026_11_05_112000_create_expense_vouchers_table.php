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
        Schema::create('expense_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voucher_number', 50); // e.g. EXP-20261105-0001
            $table->string('paid_to', 150); // payee vendor or staff member
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->string('payment_method', 30)->default('cash'); // 'cash', 'mpesa', 'bank_transfer', 'cheque'
            $table->string('reference_number', 100)->nullable(); // M-Pesa ref or cheque #
            $table->timestamp('expense_date')->useCurrent();
            $table->text('description')->nullable();
            $table->string('receipt_file_path', 255)->nullable();
            $table->enum('status', ['draft', 'approved', 'paid', 'rejected'])->default('approved');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'voucher_number']);
            $table->index(['pharmacy_id', 'branch_id', 'expense_date']);
            $table->index(['status', 'expense_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_vouchers');
    }
};
