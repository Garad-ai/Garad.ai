<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_agents', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_agents', 'average_impressions')) {
                $table->unsignedBigInteger('average_impressions')->nullable()->after('post_generation_status');
            }

            if (! Schema::hasColumn('ext_social_media_agents', 'average_engagement')) {
                $table->decimal('average_engagement', 10, 2)->nullable()->after('average_impressions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_agents', function (Blueprint $table) {
            if (Schema::hasColumn('ext_social_media_agents', 'average_engagement')) {
                $table->dropColumn('average_engagement');
            }

            if (Schema::hasColumn('ext_social_media_agents', 'average_impressions')) {
                $table->dropColumn('average_impressions');
            }
        });
    }
};
