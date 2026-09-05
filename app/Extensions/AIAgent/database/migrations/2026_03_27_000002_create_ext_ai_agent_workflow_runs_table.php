<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ai_agent_workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('workflow_id')->nullable()->index();
            $table->string('status')->default('running');
            $table->json('trigger_data')->nullable();
            $table->json('steps_output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_agent_workflow_runs');
    }
};
