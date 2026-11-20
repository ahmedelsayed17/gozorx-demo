<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name', 160)->nullable();
            $table->string('phone', 30)->nullable()->unique();
            $table->string('role', 20)->default('buyer')->index();
            $table->string('governorate', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('is_active')->default(true);
        });
        DB::table('users')->whereNull('full_name')->update(['full_name' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropIndex(['role']);
            $table->dropColumn(['full_name','phone','role','governorate','city','is_active']);
        });
    }
};
