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
        Schema::create('vendor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // UMKM pembuat ulasan
            $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete(); // Vendor yang diulas
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment');
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['vendor_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
    }
};
