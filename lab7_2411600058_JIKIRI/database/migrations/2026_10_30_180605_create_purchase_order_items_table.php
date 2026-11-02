<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);                       // price AT THE TIME of the order
            $table->unsignedInteger('received_quantity')->default(0);   // for partial receipts
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_id']);        // one line per product per order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
