<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_image_pro', function (Blueprint $table) {
            $table->timestamp('publish_requested_at')->nullable();
            $table->timestamp('publish_reviewed_at')->nullable();
            $table->unsignedBigInteger('publish_reviewed_by')->nullable();

            $table->foreign('publish_reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('publish_requested_at');
            $table->index(['published', 'publish_reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_image_pro', function (Blueprint $table) {
            $table->dropForeign(['publish_reviewed_by']);
            $table->dropIndex(['ai_image_pro_publish_requested_at_index']);
            $table->dropIndex(['ai_image_pro_published_publish_reviewed_at_index']);
            $table->dropColumn([
                'publish_requested_at',
                'publish_reviewed_at',
                'publish_reviewed_by',
            ]);
        });
    }
};
