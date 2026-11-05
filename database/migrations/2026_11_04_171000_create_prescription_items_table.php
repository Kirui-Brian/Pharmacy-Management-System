<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('dosage_instruction');
            $table->string('frequency')->nullable();
            $table->integer('duration_days')->default(1);
            $table->decimal('quantity_prescribed', 10, 2);
            $table->decimal('quantity_dispensed', 10, 2)->default(0.00);
            $table->decimal('remaining_quantity', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['prescription_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
