
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_settlements', function (Blueprint $table) {
            $table->id();

            // One settlement per supplier within an order.
            // Preserve financial records if an order is deleted.
            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('supplier_profiles')
                ->restrictOnDelete();

            // Financial calculation snapshot
            $table->decimal('products_amount', 14, 2);
            $table->decimal('shipping_amount', 14, 2)->default(0);

            $table->decimal('commission_rate', 5, 2)->default(6.00);
            $table->decimal('commission_amount', 14, 2);

            $table->decimal('deposit_amount', 14, 2);
            $table->decimal('direct_balance_amount', 14, 2);

            // Total supplier entitlement after platform commission
            $table->decimal('supplier_total_amount', 14, 2);

            // Amount GOZORX must transfer to the supplier
            $table->decimal('transfer_amount', 14, 2);

            // Actual amount recorded by admin after external transfer
            $table->decimal('transferred_amount', 14, 2)->nullable();

            // pending: awaiting delivery and direct-balance confirmation
            // ready: eligible for admin transfer
            // paid: external transfer recorded and confirmed
            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('transferred_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('transaction_reference', 150)->nullable();
            $table->text('notes')->nullable();

            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('transferred_at')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['order_id', 'supplier_id'],
                'supplier_settlements_order_supplier_unique'
            );

            $table->index(
                ['supplier_id', 'status'],
                'supplier_settlements_supplier_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_settlements');
    }
};
