<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'public_username')) {
                $table->string('public_username')->nullable()->after('email')->unique();
            }

            if (! Schema::hasColumn('users', 'profile_visibility')) {
                $table->string('profile_visibility')->default('private')->after('public_username');
            }

            if (! Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('profile_visibility');
            }

            if (! Schema::hasColumn('users', 'website')) {
                $table->string('website')->nullable()->after('bio');
            }

            if (! Schema::hasColumn('users', 'allow_mentions')) {
                $table->boolean('allow_mentions')->default(true)->after('website');
            }

            if (! Schema::hasColumn('users', 'allow_collaboration_requests')) {
                $table->boolean('allow_collaboration_requests')->default(true)->after('allow_mentions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'public_username',
                'profile_visibility',
                'bio',
                'website',
                'allow_mentions',
                'allow_collaboration_requests',
            ]);
        });
    }
};
