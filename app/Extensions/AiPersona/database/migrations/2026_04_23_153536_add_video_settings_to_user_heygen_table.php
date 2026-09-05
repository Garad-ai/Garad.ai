<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heygen', function (Blueprint $table) {
            $table->string('avatar_style')->nullable()->after('input_text');
            $table->boolean('matting')->nullable()->after('avatar_style');
            $table->boolean('caption')->nullable()->after('matting');
            $table->string('circle_background_color', 7)->nullable()->after('caption');
            $table->string('voice_id')->nullable()->after('circle_background_color');
            $table->decimal('voice_speed', 3, 1)->nullable()->after('voice_id');
            $table->smallInteger('voice_pitch')->nullable()->after('voice_speed');
            $table->string('voice_emotion')->nullable()->after('voice_pitch');
            $table->unsignedInteger('dimension_width')->nullable()->after('voice_emotion');
            $table->unsignedInteger('dimension_height')->nullable()->after('dimension_width');
        });
    }

    public function down(): void
    {
        Schema::table('user_heygen', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_style',
                'matting',
                'caption',
                'circle_background_color',
                'voice_id',
                'voice_speed',
                'voice_pitch',
                'voice_emotion',
                'dimension_width',
                'dimension_height',
            ]);
        });
    }
};
