<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('quote_status', 30)->default('not_applicable');
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->string('midtrans_transaction_status', 50)->nullable();
        });

        Schema::table('order_participants', function (Blueprint $table) {
            $table->string('quote_status', 30)->default('awaiting_vendor');
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->string('midtrans_transaction_status', 50)->nullable();
        });

        DB::table('orders')
            ->where('type', 'langsung')
            ->whereNotNull('vendor_user_id')
            ->update([
                'quote_status' => DB::raw(
                    "CASE WHEN unit_price IS NULL THEN 'awaiting_vendor' ELSE 'accepted' END",
                ),
            ]);

        DB::table('order_participants')
            ->whereIn('order_id', DB::table('orders')
                ->select('id')
                ->where('type', 'patungan')
                ->whereNotNull('unit_price'))
            ->update(['quote_status' => 'accepted']);
    }

    public function down(): void
    {
        Schema::table('order_participants', function (Blueprint $table) {
            $table->dropColumn([
                'quote_status',
                'midtrans_transaction_id',
                'payment_type',
                'midtrans_transaction_status',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'quote_status',
                'midtrans_transaction_id',
                'payment_type',
                'midtrans_transaction_status',
            ]);
        });
    }
};
