<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_presentations', function (Blueprint $table) {
            $table->string('engine')->default('fal')->after('status');
            $table->string('generation_id')->nullable()->after('style');
            $table->string('gamma_url')->nullable()->after('generation_id');
            $table->string('pdf_url')->nullable()->after('gamma_url');
            $table->string('pptx_url')->nullable()->after('pdf_url');
            $table->decimal('credits_deducted_amount', 12, 4)->nullable()->after('completed_slides');
            $table->timestamp('credits_deducted_at')->nullable()->after('credits_deducted_amount');
            $table->timestamp('completed_at')->nullable()->after('credits_deducted_at');

            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_presentations', function (Blueprint $table) {
            $table->dropIndex(['generation_id']);
            $table->dropColumn([
                'engine',
                'generation_id',
                'gamma_url',
                'pdf_url',
                'pptx_url',
                'credits_deducted_amount',
                'credits_deducted_at',
                'completed_at',
            ]);
        });
    }
};
