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
        Schema::create('vendor_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_reference', 80);
            $table->string('name', 150);
            $table->string('category', 30);
            $table->string('material', 100);
            $table->unsignedInteger('minimum_order_quantity');
            $table->text('description');
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique(['vendor_user_id', 'client_reference']);
            $table->index(['status', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_listings');
    }
};
