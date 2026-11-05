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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // e.g. "Box of 100 Tablets", "Tablet", "Bottle"
            $table->string('short_name', 30); // e.g. "box", "tab", "btl"
            $table->string('base_unit', 50)->default('unit'); // e.g. "tablet", "capsule", "ml", "unit"
            $table->enum('operator', ['*', '/'])->default('*');
            $table->decimal('operation_value', 10, 4)->default(1.0000); // Multiplier to single dispensing units
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
