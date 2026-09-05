<?php

use App\Enums\AiImageStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_pro_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_ip')->nullable();

            // Core info
            $table->string('model')->nullable();
            $table->string('engine')->nullable();
            $table->text('prompt');

            // Flexible parameters (includes negative_prompt, style, etc.)
            $table->json('params')->nullable();

            // Status tracking
            $table->string('status')->default(AiImageStatusEnum::PENDING->value);

            // Output and metadata
            $table->json('generated_images')->nullable();
            $table->json('metadata')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_pro_image');
    }
};
