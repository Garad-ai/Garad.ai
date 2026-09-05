<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_phone_call_agent_trains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('ext_phone_call_agents')->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('doc_id')->nullable();
            $table->string('name')->nullable();
            $table->enum('type', ['url', 'file', 'text']);
            $table->string('file')->nullable();
            $table->text('url')->nullable();
            $table->text('text')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_phone_call_agent_trains');
    }
};
