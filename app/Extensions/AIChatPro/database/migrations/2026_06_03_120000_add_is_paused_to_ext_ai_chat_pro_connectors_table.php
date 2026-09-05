<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_chat_pro_connectors', function (Blueprint $table): void {
            if (! Schema::hasColumn('ext_ai_chat_pro_connectors', 'is_paused')) {
                $table->boolean('is_paused')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_chat_pro_connectors', function (Blueprint $table): void {
            if (Schema::hasColumn('ext_ai_chat_pro_connectors', 'is_paused')) {
                $table->dropColumn('is_paused');
            }
        });
    }
};
