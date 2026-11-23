
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_profiles', function (Blueprint $table) {
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::create('supplier_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_profile_id')
                ->constrained('supplier_profiles')
                ->cascadeOnDelete();

            $table->string('document_type', 40);
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('status', 30)
                ->default('pending_review')
                ->index();

            $table->text('review_note')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampTz('reviewed_at')->nullable();

            $table->timestampsTz();

            $table->unique(
                ['supplier_profile_id', 'document_type'],
                'supplier_document_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');

        Schema::table('supplier_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'latitude',
                'longitude',
            ]);
        });
    }
};
