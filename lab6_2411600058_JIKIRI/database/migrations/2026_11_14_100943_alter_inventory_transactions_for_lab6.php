<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->enum('type', ['stock_in', 'stock_out', 'adjustment'])->change();
            $table->unsignedInteger('balance_after')->nullable()->after('quantity');
            $table->text('notes')->nullable()->after('reference');

            // never cascade: deleting a product must not erase its audit trail
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();

            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'created_at']);
            $table->dropIndex(['type']);
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->dropColumn(['balance_after', 'notes']);
            $table->enum('type', ['stock_in', 'stock_out'])->change();
        });
    }
};