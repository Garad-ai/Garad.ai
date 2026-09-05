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
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            if (Schema::hasColumn('ext_social_media_posts', 'has_replicate')) {
                return;
            }

            $table->boolean('has_replicate')
                ->default(false)
                ->after('is_repeated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            $table->dropColumn('has_replicate');
        });
    }
};
