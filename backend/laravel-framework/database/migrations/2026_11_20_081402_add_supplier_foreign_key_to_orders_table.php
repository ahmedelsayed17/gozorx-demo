
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill supplier_id only when a legacy order
        // belongs to exactly one distinct supplier.
        $orders = DB::table('orders')
            ->whereNull('supplier_id')
            ->pluck('id');

        foreach ($orders as $orderId) {
            $supplierIds = DB::table('supplier_orders')
                ->where('order_id', $orderId)
                ->distinct()
                ->pluck('supplier_id');

            if ($supplierIds->count() > 1) {
                // A legacy multi-supplier parent order cannot
                // be represented by one order-level supplier_id.
                continue;
            }

            if ($supplierIds->count() === 1) {
                $supplierId = $supplierIds->first();

                if (!DB::table('supplier_profiles')
                    ->where('id', $supplierId)
                    ->exists()) {
                    throw new RuntimeException(
                        "Supplier {$supplierId} does not exist for order {$orderId}."
                    );
                }

                DB::table('orders')
                    ->where('id', $orderId)
                    ->whereNull('supplier_id')
                    ->update(['supplier_id' => $supplierId]);
            }
        }

        // Ensure all non-null supplier references are valid.
        $invalidOrders = DB::table('orders')
            ->whereNotNull('supplier_id')
            ->whereNotIn(
                'supplier_id',
                DB::table('supplier_profiles')->select('id')
            )
            ->count();

        if ($invalidOrders > 0) {
            throw new RuntimeException(
                'Cannot add supplier foreign key: invalid supplier references exist.'
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('supplier_profiles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });
    }
};
