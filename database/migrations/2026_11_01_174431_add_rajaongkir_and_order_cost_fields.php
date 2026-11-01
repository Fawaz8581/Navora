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
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->string('rajaongkir_area_id', 32)->nullable();
            $table->string('rajaongkir_area_name')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_area_id', 32)->nullable();
            $table->string('delivery_area_name')->nullable();
            $table->unsignedBigInteger('subtotal_amount')->nullable();
            $table->unsignedBigInteger('shipping_amount')->nullable();
            $table->unsignedBigInteger('platform_fee_amount')->nullable();
            $table->unsignedInteger('shipping_weight_per_unit_grams')->nullable();
            $table->string('shipping_courier', 32)->nullable();
            $table->string('shipping_service', 100)->nullable();
        });

        Schema::table('order_participants', function (Blueprint $table) {
            $table->text('delivery_address')->nullable();
            $table->string('delivery_area_id', 32)->nullable();
            $table->string('delivery_area_name')->nullable();
            $table->unsignedBigInteger('subtotal_amount')->nullable();
            $table->unsignedBigInteger('shipping_amount')->nullable();
            $table->unsignedBigInteger('platform_fee_amount')->nullable();
            $table->string('shipping_courier', 32)->nullable();
            $table->string('shipping_service', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_participants', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_address',
                'delivery_area_id',
                'delivery_area_name',
                'subtotal_amount',
                'shipping_amount',
                'platform_fee_amount',
                'shipping_courier',
                'shipping_service',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_area_id',
                'delivery_area_name',
                'subtotal_amount',
                'shipping_amount',
                'platform_fee_amount',
                'shipping_weight_per_unit_grams',
                'shipping_courier',
                'shipping_service',
            ]);
        });

        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn(['rajaongkir_area_id', 'rajaongkir_area_name']);
        });
    }
};
