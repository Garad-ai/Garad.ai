<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_social_media_analyses')) {
            return;
        }

        Schema::create('ext_social_media_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->bigInteger('agent_id')->nullable();
            $table->text('summary')->nullable();
            $table->longText('report_text');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
        });

    }

    public function down(): void {}
};
