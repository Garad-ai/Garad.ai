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
            $table->boolean('booking_enabled')->default(false)->after('is_favorite');
            $table->string('booking_provider')->nullable()->after('booking_enabled');
            $table->string('booking_event_type_id')->nullable()->after('booking_provider');
            $table->string('booking_api_key', 500)->nullable()->after('booking_event_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_phone_call_agents', function (Blueprint $table) {
            $table->dropColumn(['booking_enabled', 'booking_provider', 'booking_event_type_id', 'booking_api_key']);
        });
    }
};
