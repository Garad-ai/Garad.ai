<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->foreignId('crm_invoice_id')->nullable()->after('crm_project_id')->constrained('crm_invoices')->nullOnDelete();
            $table->index('crm_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropForeign(['crm_invoice_id']);
            $table->dropColumn('crm_invoice_id');
        });
    }
};
