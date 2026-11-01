<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('vendor_listings')
            ->where('status', 'active')
            ->update(['status' => 'pending']);
    }

    /**
     * Keep listings pending on rollback so an unreviewed listing is not republished.
     */
    public function down(): void {}
};
