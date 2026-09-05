<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ads')->insert([
            [
                'type'       => 'chat-pro-top-header-section-728x90',
                'code'       => '',
                'status'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('ads')->where('type', 'chat-pro-top-header-section-728x90')->delete();
    }
};
