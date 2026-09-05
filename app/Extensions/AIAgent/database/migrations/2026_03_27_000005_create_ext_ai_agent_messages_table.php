<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ai_agent_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('channel_id')->nullable()->index();
            $table->bigInteger('workflow_run_id')->nullable()->index();
            $table->string('direction')->default('outbound');
            $table->string('sender_id')->nullable();
            $table->text('content')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_agent_messages');
    }
};
