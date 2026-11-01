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
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unsignedBigInteger('total_amount')->nullable();
            $table->string('payment_status', 30)->default('not_required');
            $table->string('payment_order_id')->nullable()->unique();
            $table->string('snap_token')->nullable();
            $table->text('snap_redirect_url')->nullable();
            $table->timestamp('paid_at')->nullable();
        });

        Schema::table('order_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_amount')->nullable();
            $table->string('payment_status', 30)->default('awaiting_quote');
            $table->string('payment_order_id')->nullable()->unique();
            $table->string('snap_token')->nullable();
            $table->text('snap_redirect_url')->nullable();
            $table->timestamp('paid_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_participants', function (Blueprint $table) {
            $table->dropUnique(['payment_order_id']);
            $table->dropColumn([
                'payment_amount',
                'payment_status',
                'payment_order_id',
                'snap_token',
                'snap_redirect_url',
                'paid_at',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['payment_order_id']);
            $table->dropColumn([
                'unit_price',
                'total_amount',
                'payment_status',
                'payment_order_id',
                'snap_token',
                'snap_redirect_url',
                'paid_at',
            ]);
        });
    }
};
