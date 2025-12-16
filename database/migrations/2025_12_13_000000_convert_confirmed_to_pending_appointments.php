<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change all confirmed appointments to pending
        DB::table('appointments')->where('status', 'confirmed')->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert pending appointments to confirmed (only those that were migrated) — cannot reliably distinguish, so do nothing.
        // Note: reversing is intentionally left as a noop because data migration is destructive.
    }
};
