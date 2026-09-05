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
            $table->string('meta_template_name')->nullable()->after('ai_reply');
            $table->string('meta_template_language')->nullable()->after('meta_template_name');
            $table->json('meta_body_variables')->nullable()->after('meta_template_language');
        });
    }

    public function down(): void
    {
        Schema::table('ext_marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn(['meta_template_name', 'meta_template_language', 'meta_body_variables']);
        });
    }
};
