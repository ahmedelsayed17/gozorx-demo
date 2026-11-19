<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('deposit_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('payment_type', 20);
            $table->string('payment_method', 30)->nullable();
            $table->decimal('amount', 14, 2);

            $table->string('status', 30)->default('pending')->index();

            $table->string('transaction_reference', 160)->nullable();
            $table->string('receipt_path')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('reviewed_at')->nullable();

            $table->foreignId('collected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('collected_at')->nullable();

            $table->text('review_note')->nullable();

            $table->timestampsTz();

            $table->index(['order_id', 'payment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'balance_amount']);
        });
    }
};
