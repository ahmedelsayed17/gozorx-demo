<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name_ar',180); $table->string('name_en',180)->nullable();
            $table->string('slug',200)->unique(); $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); $table->timestampsTz();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->foreignId('supplier_id')->constrained('supplier_profiles')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_ar',220); $table->string('name_en',220)->nullable(); $table->text('description')->nullable();
            $table->string('unit_name',50)->default('شتلة'); $table->decimal('unit_price',14,2);
            $table->unsignedInteger('minimum_order_quantity')->default(1); $table->unsignedInteger('available_quantity')->nullable();
            $table->string('status',24)->default('draft')->index(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable(); $table->timestampsTz();
            $table->index(['supplier_id','status']); $table->index(['category_id','status']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action',120); $table->string('entity_type',100); $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('details')->default('{}'); $table->ipAddress('ip_address')->nullable(); $table->timestampTz('created_at')->useCurrent();
            $table->index(['entity_type','entity_id']); $table->index(['actor_user_id','created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('audit_logs'); Schema::dropIfExists('products'); Schema::dropIfExists('categories');
    }
};
