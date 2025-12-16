<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('services')) {
            return; // Services table doesn't exist yet, skip this migration
        }

        // Add original_price column if it doesn't exist
        if (!Schema::hasColumn('services', 'original_price')) {
            Schema::table('services', function (Blueprint $table) {
                $table->decimal('original_price', 10, 2)->nullable()->after('price');
            });
        }

        // Add clinic_name column if it doesn't exist
        if (!Schema::hasColumn('services', 'clinic_name')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('clinic_name', 50)->nullable()->after('duration_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('services')) {
            return;
        }

        // Drop original_price column if it exists
        if (Schema::hasColumn('services', 'original_price')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('original_price');
            });
        }

        // Drop clinic_name column if it exists
        if (Schema::hasColumn('services', 'clinic_name')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('clinic_name');
            });
        }
    }
};

