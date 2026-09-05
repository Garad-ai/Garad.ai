<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_whatsapp_channels', function (Blueprint $table) {
            $table->string('whatsapp_provider')->default('twilio')->after('user_id');
            $table->string('meta_access_token')->nullable()->after('whatsapp_environment');
            $table->string('meta_phone_number_id')->nullable()->after('meta_access_token');
            $table->string('meta_verify_token')->nullable()->after('meta_phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::table('ext_whatsapp_channels', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_provider', 'meta_access_token', 'meta_phone_number_id', 'meta_verify_token']);
        });
    }
};
