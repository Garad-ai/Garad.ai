<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('crm_deal_id')->nullable()->after('crm_company_id')->constrained('crm_deals')->nullOnDelete();
            $table->string('discount_type')->default('fixed')->after('total');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('amount_paid', 15, 2)->default(0)->after('discount_value');
            $table->string('from_name')->nullable()->after('notes');
            $table->string('from_email')->nullable()->after('from_name');
            $table->string('from_phone')->nullable()->after('from_email');
            $table->text('from_address')->nullable()->after('from_phone');
        });

        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->after('quantity');
            $table->integer('sort_order')->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['crm_deal_id']);
            $table->dropColumn(['crm_deal_id', 'discount_type', 'discount_value', 'amount_paid', 'from_name', 'from_email', 'from_phone', 'from_address']);
        });

        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['unit', 'sort_order']);
        });
    }
};
