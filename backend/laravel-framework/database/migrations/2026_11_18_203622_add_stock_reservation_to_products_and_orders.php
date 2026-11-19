<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reserved_quantity')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestampTz('deposit_expires_at')->nullable();
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->timestampTz('stock_released_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropColumn('stock_released_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('deposit_expires_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reserved_quantity');
        });
    }
};
