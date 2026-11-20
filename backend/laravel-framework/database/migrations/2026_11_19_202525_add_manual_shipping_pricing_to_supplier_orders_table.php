


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->timestampTz('shipping_cost_set_at')->nullable();
            $table->timestampTz('deposit_expires_at')->nullable();
        });

        Schema::create('supplier_order_shipping_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_order_id')
                ->constrained('supplier_orders')
                ->cascadeOnDelete();

            $table->foreignId('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('old_shipping_cost', 14, 2)->nullable();
            $table->decimal('new_shipping_cost', 14, 2);

            $table->text('internal_notes')->nullable();

            $table->timestampsTz();

            $table->index(['supplier_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_shipping_histories');

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_cost_set_at',
                'deposit_expires_at',
            ]);
        });
    }
};
