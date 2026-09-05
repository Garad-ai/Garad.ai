<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('generation_id')->unique();
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->string('format')->default('presentation'); // presentation, document, social
            $table->string('theme_name')->nullable();
            $table->integer('num_cards')->nullable();
            $table->text('input_text')->nullable();
            $table->json('request_data')->nullable(); // Store full request for reference
            $table->json('response_data')->nullable(); // Store API response
            $table->string('gamma_url')->nullable(); // The final Gamma URL
            $table->string('pdf_url')->nullable(); // PDF export URL if requested
            $table->string('pptx_url')->nullable(); // PPTX export URL if requested
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_presentations');
    }
};
