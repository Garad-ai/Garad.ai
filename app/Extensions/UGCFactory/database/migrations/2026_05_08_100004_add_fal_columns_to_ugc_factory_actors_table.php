<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ugc_factory_actors', function (Blueprint $table) {
            if (! Schema::hasColumn('ugc_factory_actors', 'fal_request_id')) {
                $table->string('fal_request_id')->nullable()->after('prompt');
            }
            if (! Schema::hasColumn('ugc_factory_actors', 'fal_entity')) {
                $table->string('fal_entity')->nullable()->after('fal_request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ugc_factory_actors', function (Blueprint $table) {
            if (Schema::hasColumn('ugc_factory_actors', 'fal_entity')) {
                $table->dropColumn('fal_entity');
            }
            if (Schema::hasColumn('ugc_factory_actors', 'fal_request_id')) {
                $table->dropColumn('fal_request_id');
            }
        });
    }
};
