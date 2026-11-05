<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable'); // payable_type (Sale, etc.), payable_id
            $table->enum('payment_method', ['cash', 'mpesa', 'card', 'bank_transfer', 'credit']);
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_tendered', 14, 2)->nullable();
            $table->decimal('change_returned', 14, 2)->default(0.00);
            $table->string('reference_number')->nullable()->index(); // e.g. M-Pesa transaction code
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('payment_date')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pharmacy_id', 'branch_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
