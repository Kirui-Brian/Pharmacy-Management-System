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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('contact_person', 255)->nullable();
            $table->string('phone', 50);
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_pin', 50)->nullable();
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('outstanding_balance', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['pharmacy_id', 'is_active']);
            $table->index('outstanding_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
