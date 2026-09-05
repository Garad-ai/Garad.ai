<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_fall', function (Blueprint $table) {
            $table->string('resolution')->nullable()->after('duration_seconds');
            $table->string('aspect_ratio')->nullable()->after('resolution');
        });
    }

    public function down(): void
    {
        Schema::table('user_fall', function (Blueprint $table) {
            $table->dropColumn(['resolution', 'aspect_ratio']);
        });
    }
};
