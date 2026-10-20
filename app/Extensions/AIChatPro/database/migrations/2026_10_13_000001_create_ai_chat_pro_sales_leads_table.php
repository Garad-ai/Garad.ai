<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_pro_sales_leads')) {
            return;
        }

        Schema::create('ai_chat_pro_sales_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('company_size', 40);
            $table->string('company_name', 160);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 190)->index();
            $table->string('phone', 60);
            $table->string('interest', 120);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->string('ip', 64)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_pro_sales_leads');
    }
};
