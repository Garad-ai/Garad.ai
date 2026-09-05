<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_heygen', function (Blueprint $table) {
            $table->string('locale')->nullable()->after('voice_emotion');
        });
    }

    public function down(): void
    {
        Schema::table('user_heygen', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
