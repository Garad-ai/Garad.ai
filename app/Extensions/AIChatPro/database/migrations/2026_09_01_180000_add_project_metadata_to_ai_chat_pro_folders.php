<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_chat_pro_folders')) {
            return;
        }

        if (! Schema::hasColumn('ai_chat_pro_folders', 'description')) {
            Schema::table('ai_chat_pro_folders', function (Blueprint $table): void {
                $table->text('description')->nullable()->after('name');
            });
        }

        if (! Schema::hasColumn('ai_chat_pro_folders', 'instructions')) {
            Schema::table('ai_chat_pro_folders', function (Blueprint $table): void {
                $table->text('instructions')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_chat_pro_folders')) {
            return;
        }

        if (Schema::hasColumn('ai_chat_pro_folders', 'instructions')) {
            Schema::table('ai_chat_pro_folders', function (Blueprint $table): void {
                $table->dropColumn('instructions');
            });
        }

        if (Schema::hasColumn('ai_chat_pro_folders', 'description')) {
            Schema::table('ai_chat_pro_folders', function (Blueprint $table): void {
                $table->dropColumn('description');
            });
        }
    }
};
