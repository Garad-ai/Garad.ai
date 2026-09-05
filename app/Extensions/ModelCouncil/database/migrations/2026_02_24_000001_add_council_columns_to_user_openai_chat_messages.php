<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('user_openai_chat_messages', 'is_council')) {
                $table->boolean('is_council')->default(false);
            }

            if (! Schema::hasColumn('user_openai_chat_messages', 'council_response')) {
                $table->json('council_response')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_openai_chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('user_openai_chat_messages', 'council_response')) {
                $table->dropColumn('council_response');
            }

            if (Schema::hasColumn('user_openai_chat_messages', 'is_council')) {
                $table->dropColumn('is_council');
            }
        });
    }
};
