<?php

use App\Extensions\ElevenLabsVoiceChat\System\Enum\TrainTypeEnum;
use App\Models\User;
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
        Schema::create('voice_chat_bot_trains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->references('id')->on('voice_chat_bots')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('doc_id')->nullable();
            $table->string('name');

            $table->enum('type', TrainTypeEnum::toArray());
            $table->string('text')->nullable();
            $table->string('url')->nullable();
            $table->string('file')->nullable();

            $table->datetime('trained_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_chat_bot_trains');
    }
};
