<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_captions_videos', function (Blueprint $table) {
            $table->text('source_url')->nullable()->change();
            $table->text('output_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_captions_videos', function (Blueprint $table) {
            $table->string('source_url')->nullable()->change();
            $table->string('output_url')->nullable()->change();
        });
    }
};
