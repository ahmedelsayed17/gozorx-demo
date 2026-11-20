
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->decimal('deposit_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->timestampTz('stock_committed_at')->nullable();
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('supplier_order_id')
                ->nullable()
                ->constrained('supplier_orders')
                ->nullOnDelete();

            $table->index(
                ['supplier_order_id', 'payment_type'],
                'payment_supplier_order_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('payment_supplier_order_type_index');
            $table->dropConstrainedForeignId('supplier_order_id');
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_amount',
                'balance_amount',
                'stock_committed_at',
            ]);
        });
    }
};
