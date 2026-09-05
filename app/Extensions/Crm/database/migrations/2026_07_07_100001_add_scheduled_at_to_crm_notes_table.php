<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_notes', function (Blueprint $table): void {
            $table->dateTime('scheduled_at')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('crm_notes', function (Blueprint $table): void {
            $table->dropColumn('scheduled_at');
        });
    }
};
