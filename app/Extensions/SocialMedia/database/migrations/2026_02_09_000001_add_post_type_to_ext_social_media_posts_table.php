<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_posts', 'post_type')) {
                $table->string('post_type')->default('post')->after('social_media_platform');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            $table->dropColumn('post_type');
        });
    }
};
