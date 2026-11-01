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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_contact_name')->nullable();
            $table->string('delivery_contact_phone', 40)->nullable();
            $table->unsignedBigInteger('target_unit_price')->nullable();
        });

        Schema::table('order_participants', function (Blueprint $table) {
            $table->string('delivery_contact_name')->nullable();
            $table->string('delivery_contact_phone', 40)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_participants', function (Blueprint $table) {
            $table->dropColumn(['delivery_contact_name', 'delivery_contact_phone']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_contact_name',
                'delivery_contact_phone',
                'target_unit_price',
            ]);
        });
    }
};
