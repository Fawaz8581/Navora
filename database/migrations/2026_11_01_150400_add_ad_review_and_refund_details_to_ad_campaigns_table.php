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
            $table->string('midtrans_transaction_id')->nullable()->index();
            $table->string('payment_type', 50)->nullable();
            $table->string('midtrans_transaction_status', 30)->nullable();
            $table->text('review_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('refund_key')->nullable()->unique();
            $table->string('refund_status', 30)->default('not_requested');
            $table->timestamp('refund_requested_at')->nullable();
        });

        DB::table('ad_packages')
            ->where('placement', 'top')
            ->update(['price' => 200000]);
        DB::table('ad_packages')
            ->where('placement', 'sidebar')
            ->update(['price' => 100000]);

        foreach ([
            'top' => ['Banner Atas 1200x300', 1200, 300, 200000],
            'sidebar' => ['Banner Samping 300x600', 300, 600, 100000],
        ] as $placement => [$name, $width, $height, $price]) {
            if (! DB::table('ad_packages')
                ->where('placement', $placement)
                ->where('is_active', true)
                ->exists()) {
                DB::table('ad_packages')->insert([
                    'name' => $name,
                    'placement' => $placement,
                    'width' => $width,
                    'height' => $height,
                    'price' => $price,
                    'duration_days' => 30,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropUnique(['refund_key']);
            $table->dropIndex(['midtrans_transaction_id']);
            $table->dropColumn([
                'midtrans_transaction_id',
                'payment_type',
                'midtrans_transaction_status',
                'review_reason',
                'reviewed_by',
                'reviewed_at',
                'refund_key',
                'refund_status',
                'refund_requested_at',
            ]);
        });
    }
};
