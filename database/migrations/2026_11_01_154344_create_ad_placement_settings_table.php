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
        Schema::create('ad_placement_settings', function (Blueprint $table) {
            $table->id();
            $table->string('placement')->unique();
            $table->unsignedSmallInteger('max_active_ads')->default(3);
            $table->timestamps();
        });

        DB::table('ad_placement_settings')->insert([
            [
                'placement' => 'top',
                'max_active_ads' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'placement' => 'sidebar',
                'max_active_ads' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_placement_settings');
    }
};
