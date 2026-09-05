<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            $table->json('post_metrics')->nullable();
            $table->timestamp('post_metric_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            $table->dropColumn('post_metrics', 'post_metric_at');
        });
    }
};
