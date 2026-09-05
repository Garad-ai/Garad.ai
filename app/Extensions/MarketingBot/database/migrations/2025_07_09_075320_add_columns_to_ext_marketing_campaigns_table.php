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
        Schema::table('ext_marketing_campaigns', function (Blueprint $table) {
            $table->text('instruction')->nullable()->after('witch_campaign_question');
            $table->boolean('ai_reply')->default(true)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn('instruction');
            $table->dropColumn('ai_reply');
        });
    }
};
