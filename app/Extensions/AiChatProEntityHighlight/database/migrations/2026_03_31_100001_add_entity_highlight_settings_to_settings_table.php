<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'ai_chat_pro_entity_highlight_enabled')) {
                $table->boolean('ai_chat_pro_entity_highlight_enabled')->default(true)->nullable();
            }

            if (! Schema::hasColumn('settings', 'ai_chat_pro_entity_highlight_max')) {
                $table->integer('ai_chat_pro_entity_highlight_max')->default(3)->nullable();
            }

            if (! Schema::hasColumn('settings', 'ai_chat_pro_entity_highlight_threshold')) {
                $table->float('ai_chat_pro_entity_highlight_threshold')->default(0.8)->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = [
                'ai_chat_pro_entity_highlight_enabled',
                'ai_chat_pro_entity_highlight_max',
                'ai_chat_pro_entity_highlight_threshold',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
