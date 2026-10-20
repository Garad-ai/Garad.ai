<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('workspace_type');
            $table->unsignedBigInteger('workspace_id');
            $table->string('role')->default('member');
            $table->json('permissions')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'workspace_type', 'workspace_id'], 'workspace_membership_user_workspace_unique');
            $table->index(['workspace_type', 'workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_memberships');
    }
};
