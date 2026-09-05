<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heygen', function (Blueprint $table) {
            $table->string('voice_type')->nullable()->after('input_text');
            $table->string('audio_asset_id')->nullable()->after('voice_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_heygen', function (Blueprint $table) {
            $table->dropColumn(['voice_type', 'audio_asset_id']);
        });
    }
};
