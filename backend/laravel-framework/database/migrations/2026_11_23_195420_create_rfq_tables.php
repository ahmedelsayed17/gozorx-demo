<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['buyer_id', 'status']);
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_request_id')
                ->constrained('rfq_requests')
                ->cascadeOnDelete();

            $table->string('name', 200);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 50)->default('قطعة');
            $table->text('specifications')->nullable();

            $table->foreignId('selected_supplier_id')
                ->nullable()
                ->constrained('supplier_profiles')
                ->nullOnDelete();

            $table->timestampTz('selected_at')->nullable();
            $table->timestampsTz();

            $table->index(['rfq_request_id', 'selected_supplier_id']);
        });

        Schema::create('rfq_item_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_item_id')
                ->constrained('rfq_items')
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('supplier_profiles')
                ->restrictOnDelete();

            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 14, 2);
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('submitted')->index();
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampsTz();

            $table->unique(
                ['rfq_item_id', 'supplier_id'],
                'rfq_item_supplier_unique'
            );

            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_item_offers');
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfq_requests');
    }
};
