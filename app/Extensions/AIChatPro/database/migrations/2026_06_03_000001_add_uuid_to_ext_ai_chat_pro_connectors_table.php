<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_chat_pro_connectors', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('ext_ai_chat_pro_connectors')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('ext_ai_chat_pro_connectors')
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

        Schema::table('ext_ai_chat_pro_connectors', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_chat_pro_connectors', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
