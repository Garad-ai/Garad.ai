<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_platforms', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_platforms', 'followers_count')) {
                $table->unsignedBigInteger('followers_count')
                    ->nullable()
                    ->after('credentials');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_platforms', function (Blueprint $table) {
            if (Schema::hasColumn('ext_social_media_platforms', 'followers_count')) {
                $table->dropColumn('followers_count');
            }
        });
    }
};
