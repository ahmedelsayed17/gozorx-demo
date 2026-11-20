
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main order: belongs to the buyer
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('order_number', 40)->unique();

            $table->string('status', 30)->default('pending_payment')->index();

            $table->string('payment_method', 30);
            $table->string('payment_status', 30)->default('pending')->index();

            $table->string('recipient_name', 160);
            $table->string('recipient_phone', 30);
            $table->string('governorate', 100);
            $table->string('city', 100);
            $table->text('address');
            $table->text('delivery_notes')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);

            $table->timestampsTz();

            $table->index(['buyer_id', 'created_at']);
        });

        // One supplier order for each supplier in the main order
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('supplier_profiles')
                ->restrictOnDelete();

            $table->string('status', 30)->default('pending')->index();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);

            $table->text('cancellation_reason')->nullable();

            $table->timestampsTz();

            $table->unique(['order_id', 'supplier_id']);
        });

        // Product snapshots at the time of purchase
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_order_id')
                ->constrained('supplier_orders')
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
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('supplier_orders');
        Schema::dropIfExists('orders');
    }
};
