<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_image_pro_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_image_pro_id')->constrained('ai_image_pro')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('guest_ip')->nullable();
            $table->timestamps();

            // Ensure a user or guest can only like once
            $table->unique(['ai_image_pro_id', 'user_id']);
            $table->index(['ai_image_pro_id', 'guest_ip']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_image_pro_likes');
    }
};
