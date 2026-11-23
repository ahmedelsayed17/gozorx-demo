<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_settlements', function (Blueprint $table) {
            $table->string('balance_payment_method', 30)->nullable();
            $table->string('balance_transaction_reference', 150)->nullable();

            $table->foreignId('balance_confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('balance_confirmed_at')->nullable();
            $table->text('balance_confirmation_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_settlements', function (Blueprint $table) {
            $table->dropForeign(['balance_confirmed_by']);

            $table->dropColumn([
                'balance_payment_method',
                'balance_transaction_reference',
                'balance_confirmed_by',
                'balance_confirmed_at',
                'balance_confirmation_notes',
            ]);
        });
    }
};
