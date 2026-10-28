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
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('category')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('product_type')->nullable();
            $table->string('packaging_type')->nullable();
            $table->string('preferred_material')->nullable();
            $table->unsignedInteger('monthly_quantity')->nullable();
            $table->string('product_categories')->nullable();
            $table->string('materials')->nullable();
            $table->unsignedInteger('minimum_order_quantity')->nullable();
            $table->unsignedInteger('monthly_capacity')->nullable();
            $table->unsignedSmallInteger('production_lead_time_days')->nullable();
            $table->unsignedTinyInteger('onboarding_step')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
