<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_pro_artifacts')) {
            return;
        }

        Schema::create('ai_chat_pro_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('user_openai_chat_id')->nullable()->index();
            $table->string('type', 30)->index();
            $table->string('title');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_openai_chat_id')->references('id')->on('user_openai_chat')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_pro_artifacts');
    }
};
