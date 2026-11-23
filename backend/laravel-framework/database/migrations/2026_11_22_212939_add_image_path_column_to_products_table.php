<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // image_path is already added by the earlier migration.
    }

    public function down(): void
    {
        // Intentionally empty to avoid dropping the column owned by the earlier migration.
    }
};
