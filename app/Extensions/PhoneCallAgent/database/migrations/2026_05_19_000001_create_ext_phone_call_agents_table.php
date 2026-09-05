<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_phone_call_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('agent_id')->nullable();
            $table->string('title');
            $table->text('welcome_message')->nullable();
            $table->text('instructions')->nullable();
            $table->string('language')->nullable();
            $table->string('ai_model')->nullable();
            $table->string('voice_id')->nullable();
            $table->string('provider')->default('elevenlabs');
            $table->integer('max_call_duration')->default(300);
            $table->integer('silence_timeout')->default(30);
            $table->boolean('active')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_phone_call_agents');
    }
};
