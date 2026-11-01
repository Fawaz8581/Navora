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
        Schema::create('vendor_listing_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_listing_id')->constrained('vendor_listings')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type', 80);
            $table->string('media_type', 10);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['vendor_listing_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_media');
    }
};
