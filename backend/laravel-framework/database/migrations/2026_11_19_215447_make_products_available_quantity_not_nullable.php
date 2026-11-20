<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('products')->whereNull('available_quantity')->exists()) {
            throw new RuntimeException(
                'Cannot make available_quantity NOT NULL: products with NULL stock exist.'
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('available_quantity')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('available_quantity')->nullable()->change();
        });
    }
};
