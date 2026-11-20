<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add order-level fields without rebuilding the legacy orders table.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->index('supplier_id');

            $table->timestampTz('shipping_cost_set_at')->nullable();
            $table->timestampTz('stock_released_at')->nullable();
            $table->timestampTz('stock_committed_at')->nullable();
        });

        // New table for individual product orders.
        // Legacy order_items and supplier_orders remain untouched.
        Schema::create('individual_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->string('product_name_ar', 220);
            $table->string('product_name_en', 220)->nullable();
            $table->string('unit_name', 50);
            $table->decimal('unit_price', 14, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 14, 2);

            $table->timestampsTz();

            $table->index('product_id');
        });

        // Independent shipping price audit history for each order.
        Schema::create('order_shipping_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('old_shipping_cost', 14, 2)->nullable();
            $table->decimal('new_shipping_cost', 14, 2);
            $table->text('internal_notes')->nullable();

            $table->timestampsTz();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipping_histories');
        Schema::dropIfExists('individual_order_items');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'shipping_cost_set_at',
                'stock_released_at',
                'stock_committed_at',
            ]);
        });
    }
};
