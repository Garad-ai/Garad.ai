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
            $table->text('booking_api_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ext_phone_call_agents', function (Blueprint $table) {
            $table->string('booking_api_key', 500)->nullable()->change();
        });
    }
};
