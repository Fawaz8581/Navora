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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->text('packaging_details');
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('menunggu_konfirmasi');
            $table->boolean('is_simulated')->default(false);
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('order_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['order_id', 'user_id']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_participants');
        Schema::dropIfExists('orders');
    }
};
