<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('ext_social_media_agent_posts', 'publishing_type')) {
                $table->string('publishing_type')->default('post')->after('post_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_agent_posts', function (Blueprint $table) {
            $table->dropColumn('publishing_type');
        });
    }
};
