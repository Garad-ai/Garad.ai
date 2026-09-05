<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ext_ai_agent_avatars')->whereNull('user_id')->delete();

        foreach (range(1, 5) as $n) {
            DB::table('ext_ai_agent_avatars')->insert([
                'user_id'    => null,
                'avatar'     => "vendor/ai-agent/images/avatars/avatar-{$n}.png",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ext_ai_agent_avatars')->whereNull('user_id')->delete();
    }
};
