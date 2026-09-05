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
        Schema::table('user_openai_chat', function (Blueprint $table) {
            if (! Schema::hasColumn('user_openai_chat', 'social_media_analysis_id')) {
                $table->unsignedBigInteger('social_media_analysis_id')
                    ->nullable()
                    ->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
