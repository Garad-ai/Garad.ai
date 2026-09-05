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
        Schema::table('conditional_discounts', function (Blueprint $table) {
            $table->boolean('hide_discount_for_subscribed_users')->default(true)->after('show_strikethrough_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conditional_discounts', function (Blueprint $table) {
            $table->dropColumn('hide_discount_for_subscribed_users');
        });
    }
};
