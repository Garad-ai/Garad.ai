<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_telegram_contacts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('telegram_id')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('group_chat_id')->nullable();
            $table->bigInteger('group_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_telegram_contacts');
    }
};
