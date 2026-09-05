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
        Schema::create('ext_marketing_message_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('message_id')->index();
            $table->string('model')->nullable();
            $table->string('role')->index();
            $table->text('message');
            $table->string('type')->nullable();
            $table->string('media_url')->nullable();
            $table->string('message_type')->nullable();
            $table->string('content_type')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_marketing_message_histories');
    }
};
