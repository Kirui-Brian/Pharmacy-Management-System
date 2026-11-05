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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('generic_name', 255);
            $table->string('brand_name', 255)->nullable();
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable()->index();
            $table->unsignedInteger('pack_size')->default(1);
            $table->decimal('purchase_price', 14, 4)->default(0.0000);
            $table->decimal('selling_price', 12, 2)->default(0.00);
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->unsignedInteger('reorder_level')->default(10);
            $table->unsignedInteger('max_stock_level')->default(500);
            $table->boolean('is_prescription_required')->default(false);
            $table->boolean('is_controlled')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['pharmacy_id', 'is_active']);
            $table->index('generic_name');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
