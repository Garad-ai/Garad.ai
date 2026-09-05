<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_telegram_group_subscribers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->string('client_id')->nullable();
            $table->string('group_chat_id')->nullable();
            $table->string('group_subscriber_id')->nullable();
            $table->string('group_id')->nullable();
            $table->string('unique_id')->nullable();
            $table->boolean('is_left_group')->default(false);
            $table->string('type')->nullable(); // e.g., 'member', 'admin', etc.
            $table->boolean('status')->default(true); // Active status
            $table->boolean('is_blacklist')->default(false); // Blacklist status
            $table->boolean('is_bot')->default(false); // Indicates if the subscriber is a bot
            $table->boolean('is_admin')->default(false); // Indicates if the subscriber is an admin
            $table->json('scopes')->nullable(); // JSON field for additional scopes or permissions
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_telegram_group_subscribers');
    }
};
