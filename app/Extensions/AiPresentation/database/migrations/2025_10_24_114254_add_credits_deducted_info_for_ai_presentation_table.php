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
        Schema::table('ai_presentations', function (Blueprint $table) {
            $table->decimal('credits_deducted_amount', 10, 2)->nullable();
            $table->timestamp('credits_deducted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_presentations', function (Blueprint $table) {
            $table->dropColumn('credits_deducted_amount');
            $table->dropColumn('credits_deducted_at');
        });
    }
};
