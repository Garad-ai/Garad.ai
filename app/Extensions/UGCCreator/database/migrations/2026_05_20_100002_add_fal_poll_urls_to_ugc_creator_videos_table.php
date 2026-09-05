<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ugc_creator_videos')) {
            return;
        }

        Schema::table('ugc_creator_videos', function (Blueprint $table) {
            if (! Schema::hasColumn('ugc_creator_videos', 'fal_response_url')) {
                $table->string('fal_response_url', 1024)->nullable()->after('fal_request_id');
            }
            if (! Schema::hasColumn('ugc_creator_videos', 'fal_status_url')) {
                $table->string('fal_status_url', 1024)->nullable()->after('fal_response_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ugc_creator_videos')) {
            return;
        }

        Schema::table('ugc_creator_videos', function (Blueprint $table) {
            if (Schema::hasColumn('ugc_creator_videos', 'fal_status_url')) {
                $table->dropColumn('fal_status_url');
            }
            if (Schema::hasColumn('ugc_creator_videos', 'fal_response_url')) {
                $table->dropColumn('fal_response_url');
            }
        });
    }
};
