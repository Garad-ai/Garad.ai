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
            $table->string('meta_waba_id')->nullable()->after('meta_verify_token');
        });
    }

    public function down(): void
    {
        Schema::table('ext_whatsapp_channels', function (Blueprint $table) {
            $table->dropColumn('meta_waba_id');
        });
    }
};
