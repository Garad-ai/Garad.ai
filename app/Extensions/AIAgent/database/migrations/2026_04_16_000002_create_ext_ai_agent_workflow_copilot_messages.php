<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ai_agent_workflow_copilot_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->index()->constrained('ext_ai_agent_workflows')->cascadeOnDelete();
            $table->string('role')->default('user'); // user | assistant | tool
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_agent_workflow_copilot_messages');
    }
};
