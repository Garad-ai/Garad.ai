<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('group_id')->nullable();
            $table->integer('bot_id')->nullable();
            $table->string('type')->default('telegram');
            $table->string('group_type')->nullable();
            $table->string('supergroup_subscriber_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_telegram_groups');
    }
};
