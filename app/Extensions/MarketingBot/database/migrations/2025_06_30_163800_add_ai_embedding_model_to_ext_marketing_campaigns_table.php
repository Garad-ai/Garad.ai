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
            $table->string('ai_embedding_model')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ext_marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn('ai_embedding_model');
        });
    }
};
