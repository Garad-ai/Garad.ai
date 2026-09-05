<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_analyses', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_analyses', 'summary')) {
                $table->text('summary')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_social_media_analyses');
    }
};
