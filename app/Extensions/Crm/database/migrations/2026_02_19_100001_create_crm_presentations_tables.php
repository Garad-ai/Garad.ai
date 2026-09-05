<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('title');
            $table->string('status')->default('pending');
            $table->string('style')->nullable();
            $table->unsignedTinyInteger('total_slides')->default(0);
            $table->unsignedTinyInteger('completed_slides')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('crm_deal_id');
        });

        Schema::create('crm_presentation_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_presentation_id')->constrained('crm_presentations')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('slide_type');
            $table->string('title');
            $table->text('content')->nullable();
            $table->text('image_prompt')->nullable();
            $table->string('fal_request_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('image_url')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index('crm_presentation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_presentation_slides');
        Schema::dropIfExists('crm_presentations');
    }
};
