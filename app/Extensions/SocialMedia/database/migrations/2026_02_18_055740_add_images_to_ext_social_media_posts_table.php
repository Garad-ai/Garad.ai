<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image');
        });

        DB::table('ext_social_media_posts')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->update([
                'images' => DB::raw('JSON_ARRAY(image)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('ext_social_media_posts', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
