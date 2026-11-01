<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->string('ad_title', 120)->nullable();
            $table->string('destination_url', 2048)->nullable();
        });

        if (! DB::table('ad_packages')->where('is_active', true)->exists()) {
            DB::table('ad_packages')->insert([
                'name' => 'Banner 1200x300',
                'placement' => 'top',
                'width' => 1200,
                'height' => 300,
                'price' => 200000,
                'duration_days' => 30,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['ad_title', 'destination_url']);
        });
    }
};
