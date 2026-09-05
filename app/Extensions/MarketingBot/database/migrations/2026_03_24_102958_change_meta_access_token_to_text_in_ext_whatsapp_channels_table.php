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
        Schema::table('ext_whatsapp_channels', function (Blueprint $table) {
            $table->text('meta_access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ext_whatsapp_channels', function (Blueprint $table) {
            $table->string('meta_access_token')->nullable()->change();
        });
    }
};
