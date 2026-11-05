<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_received_note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->constrained('users')->cascadeOnDelete();
            $table->string('return_number')->index();
            $table->dateTime('return_date');
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->string('reason');
            $table->enum('status', ['completed', 'pending'])->default('completed');
            $table->timestamps();

            $table->unique(['pharmacy_id', 'return_number']);
            $table->index(['pharmacy_id', 'supplier_id', 'return_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
