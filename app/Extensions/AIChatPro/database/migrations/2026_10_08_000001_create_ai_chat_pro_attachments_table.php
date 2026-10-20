<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_pro_attachments')) {
            return;
        }

        Schema::create('ai_chat_pro_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->string('storage_disk', 40);
            $table->string('storage_path');
            $table->char('checksum', 64);
            $table->string('upload_status', 20)->default('uploaded')->index();
            $table->string('processing_status', 20)->default('pending')->index();
            $table->string('extraction_status', 20)->default('pending')->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_pro_attachments');
    }
};
