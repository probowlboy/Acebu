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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('email');
            $table->date('birthday')->nullable()->after('username');
            $table->string('phone')->nullable()->after('birthday');
            $table->string('country')->nullable()->after('phone');
            $table->string('municipality')->nullable()->after('country');
            $table->string('province')->nullable()->after('municipality');
            $table->string('barangay')->nullable()->after('province');
            $table->string('zip_code', 4)->nullable()->after('barangay');
            $table->string('zone_street')->nullable()->after('zip_code');
            $table->enum('role', ['patient', 'admin'])->default('patient')->after('zone_street');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'birthday',
                'phone',
                'country',
                'municipality',
                'province',
                'barangay',
                'zip_code',
                'zone_street',
                'role'
            ]);
        });
    }
};
