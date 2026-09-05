<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_chat_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('openai_chat_category_id')->constrained('openai_chat_category')->onDelete('cascade');
            $table->string('ip_address')->nullable()->index();
            $table->text('instructions');
            $table->timestamps();

            $table->unique(['user_id', 'openai_chat_category_id'], 'user_category_unique');
            $table->index(['ip_address', 'openai_chat_category_id'], 'ip_category_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_chat_instructions');
    }
};
