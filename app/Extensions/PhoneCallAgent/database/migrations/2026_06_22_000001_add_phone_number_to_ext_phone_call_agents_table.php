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
        Schema::table('ext_phone_call_agents', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('voice_id');
            $table->string('phone_number_id')->nullable()->after('phone_number');
            $table->string('twilio_account_sid')->nullable()->after('phone_number_id');
            $table->text('twilio_auth_token')->nullable()->after('twilio_account_sid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_phone_call_agents', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'phone_number_id',
                'twilio_account_sid',
                'twilio_auth_token',
            ]);
        });
    }
};
