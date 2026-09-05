<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_phone_call_agent_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained('ext_phone_call_agent_calls')->cascadeOnDelete();
            $table->enum('role', ['user', 'agent']);
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_phone_call_agent_transcripts');
    }
};
