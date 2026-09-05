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
        Schema::create('ext_whatsapp_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('whatsapp_sid')->nullable();
            $table->string('whatsapp_token')->nullable();
            $table->string('whatsapp_phone')->nullable();
            $table->string('whatsapp_sandbox_phone')->nullable();
            $table->string('whatsapp_environment')->default('sandbox');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_whatsapp_channels');
    }
};
