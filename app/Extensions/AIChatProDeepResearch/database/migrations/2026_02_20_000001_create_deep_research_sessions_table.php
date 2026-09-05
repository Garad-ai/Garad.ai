<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deep_research_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('user_openai_chat_messages')->nullOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained('user_openai_chat')->nullOnDelete();
            $table->text('query');
            $table->string('status')->default('pending');
            $table->string('engine_used')->nullable();
            $table->string('model_used')->nullable();
            $table->string('provider_response_id')->nullable();
            $table->integer('sources_count')->default(0);
            $table->integer('searches_count')->default(0);
            $table->integer('duration_seconds')->nullable();
            $table->integer('credits_used')->default(0);
            $table->json('sources')->nullable();
            $table->json('thinking_steps')->nullable();
            $table->longText('report_output')->nullable();
            $table->longText('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deep_research_sessions');
    }
};
