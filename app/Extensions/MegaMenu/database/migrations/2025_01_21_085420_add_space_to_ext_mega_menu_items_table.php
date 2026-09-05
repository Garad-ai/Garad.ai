<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ext_mega_menu_items', function (Blueprint $table) {
            $table->integer('space')->default(0)->after('params');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_mega_menu_items', function (Blueprint $table) {
            $table->dropColumn('space');
        });
    }
};
