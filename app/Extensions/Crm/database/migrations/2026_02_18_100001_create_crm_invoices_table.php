<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->string('invoice_number', 20);
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('discount_type')->default('fixed');
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_phone')->nullable();
            $table->text('from_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'invoice_number']);
        });

        Schema::create('crm_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('crm_invoice_id');
        });

        Schema::create('crm_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_invoice_id')->constrained('crm_invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('crm_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_invoice_payments');
        Schema::dropIfExists('crm_invoice_items');
        Schema::dropIfExists('crm_invoices');
    }
};
