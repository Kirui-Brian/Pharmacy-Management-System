<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('patient_name');
            $table->foreignId('dispensed_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('dispensation_date')->index();
            $table->text('pharmacist_notes')->nullable();
            $table->enum('verification_status', ['verified', 'dispensed'])->default('dispensed');
            $table->timestamps();

            $table->index(['pharmacy_id', 'dispensation_date']);
        });

        Schema::create('dispensation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispensation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->decimal('quantity_dispensed', 10, 2);
            $table->string('dosage_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensation_items');
        Schema::dropIfExists('dispensations');
    }
};
