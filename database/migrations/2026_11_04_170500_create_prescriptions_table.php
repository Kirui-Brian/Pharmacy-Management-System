<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('patient_name');
            $table->string('prescription_number')->index();
            $table->string('prescriber_name');
            $table->string('prescriber_facility')->nullable();
            $table->string('prescriber_license')->nullable();
            $table->string('prescriber_phone')->nullable();
            $table->date('prescription_date');
            $table->text('diagnosis_notes')->nullable();
            $table->enum('status', ['pending', 'partially_dispensed', 'fully_dispensed', 'cancelled'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pharmacy_id', 'prescription_number']);
            $table->index(['pharmacy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
