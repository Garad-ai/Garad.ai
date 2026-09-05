<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove FK from crm_tasks first
        if (Schema::hasColumn('crm_tasks', 'crm_invoice_id')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('crm_tasks', function (Blueprint $table) {
                    $table->dropForeign(['crm_invoice_id']);
                });
            }

            Schema::table('crm_tasks', function (Blueprint $table) {
                $table->dropColumn('crm_invoice_id');
            });
        }

        Schema::dropIfExists('crm_invoice_payments');
        Schema::dropIfExists('crm_invoice_items');
        Schema::dropIfExists('crm_invoices');
    }

    public function down(): void
    {
        // Not reversible — the original create migrations still exist
    }
};
