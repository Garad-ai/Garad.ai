<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_council_responses')) {
            return;
        }

        Schema::create('model_council_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_openai_chat_message_id')
                ->constrained('user_openai_chat_messages')
                ->cascadeOnDelete();
            $table->string('model_slug')->index();
            $table->string('model_label');
            $table->longText('response_text')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('failed')->default(false);
            $table->integer('credits')->nullable();
            $table->timestamps();

            $table->index('user_openai_chat_message_id', 'mc_responses_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_council_responses');
    }
};
