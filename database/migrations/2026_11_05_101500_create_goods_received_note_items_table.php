<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date');
            $table->decimal('quantity_received', 12, 2);
            $table->decimal('unit_purchase_price', 12, 2);
            $table->decimal('unit_selling_price', 12, 2);
            $table->decimal('subtotal_cost', 14, 2);
            $table->timestamps();

            $table->index(['goods_received_note_id', 'product_id'], 'grn_items_product_index');
            $table->index('product_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_note_items');
    }
};
