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
        if (Schema::hasColumn('ext_telegram_groups', 'is_admin')) {
            return;
        }
        Schema::table('ext_telegram_groups', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->comment('Indicates if the user is an admin in the Telegram group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_telegram_groups', function (Blueprint $table) {
            //
        });
    }
};
