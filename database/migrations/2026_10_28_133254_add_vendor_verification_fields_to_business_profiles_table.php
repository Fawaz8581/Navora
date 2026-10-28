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
            $table->text('nib')->nullable();
            $table->string('nib_document_path')->nullable();
            $table->json('work_sample_paths')->nullable();
            $table->json('production_photo_paths')->nullable();
            $table->json('certificate_paths')->nullable();
            $table->string('verification_status', 20)->default('not_submitted');
            $table->text('verification_notes')->nullable();
            $table->timestamp('verification_submitted_at')->nullable();
            $table->timestamp('verification_reviewed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'nib',
                'nib_document_path',
                'work_sample_paths',
                'production_photo_paths',
                'certificate_paths',
                'verification_status',
                'verification_notes',
                'verification_submitted_at',
                'verification_reviewed_at',
                'verified_by',
            ]);
        });
    }
};
