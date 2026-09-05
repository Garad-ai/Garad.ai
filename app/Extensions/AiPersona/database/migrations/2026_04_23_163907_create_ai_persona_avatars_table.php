<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_persona_avatars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name');
            $table->string('source');
            $table->string('status')->index();
            $table->string('generation_id')->nullable();
            $table->string('image_key')->nullable();
            $table->string('group_id')->nullable();
            $table->string('flow_id')->nullable();
            $table->string('heygen_avatar_id')->nullable()->index();
            $table->text('preview_image_url')->nullable();
            $table->string('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('ethnicity')->nullable();
            $table->string('orientation')->nullable();
            $table->string('pose')->nullable();
            $table->string('style')->nullable();
            $table->text('appearance')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_persona_avatars');
    }
};
