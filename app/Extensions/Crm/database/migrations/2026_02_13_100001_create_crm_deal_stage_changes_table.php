<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deal_stage_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('crm_deal_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->constrained('crm_deal_stages')->cascadeOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['user_id', 'crm_deal_id']);
            $table->index('to_stage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deal_stage_changes');
    }
};
