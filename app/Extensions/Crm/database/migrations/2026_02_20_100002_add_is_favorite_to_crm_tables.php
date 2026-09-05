<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['crm_contacts', 'crm_companies', 'crm_deals', 'crm_projects'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('is_favorite')->default(false)->after('user_id');
                $t->index(['user_id', 'is_favorite']);
            });
        }
    }

    public function down(): void
    {
        $tables = ['crm_contacts', 'crm_companies', 'crm_deals', 'crm_projects'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex([$table . '_user_id_is_favorite_index']);
                $t->dropColumn('is_favorite');
            });
        }
    }
};
