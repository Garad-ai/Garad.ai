<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_phone_call_agent_call_tag_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->unique(['call_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_phone_call_agent_call_tag_pivot');
    }
};
