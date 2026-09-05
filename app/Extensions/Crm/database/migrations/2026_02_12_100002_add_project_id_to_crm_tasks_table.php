<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->foreignId('crm_project_id')->nullable()->after('crm_deal_id')->constrained('crm_projects')->nullOnDelete();
            $table->index('crm_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropForeign(['crm_project_id']);
            $table->dropColumn('crm_project_id');
        });
    }
};
