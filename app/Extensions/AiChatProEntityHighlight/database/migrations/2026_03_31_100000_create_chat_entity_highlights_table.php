<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_entity_highlights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->json('entities');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('user_openai_chat_messages')->cascadeOnDelete();
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_entity_highlights');
    }
};
