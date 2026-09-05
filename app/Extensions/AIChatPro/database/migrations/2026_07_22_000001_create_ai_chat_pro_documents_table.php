<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_pro_documents')) {
            return;
        }

        Schema::create('ai_chat_pro_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_openai_chat_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 20)->default('ready')->index();
            $table->string('original_name');
            $table->string('file_name')->nullable();
            $table->string('file_extension', 20)->nullable()->index();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('storage_disk', 40)->default('public');
            $table->string('storage_path')->nullable();
            $table->text('reference_url')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->longText('extracted_text')->nullable();
            $table->longText('chunks')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_openai_chat_id')
                ->references('id')
                ->on('user_openai_chat')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_pro_documents');
    }
};