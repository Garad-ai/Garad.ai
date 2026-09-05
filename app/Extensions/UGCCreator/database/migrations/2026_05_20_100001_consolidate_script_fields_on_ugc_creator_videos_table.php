<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidates the five separate script_* fields into a single video_details
 * column. The original schema split dialogue / voiceover / actions /
 * expressions / camera notes into individual columns; the simplified UI
 * collects all of that in one freeform textarea, so the extra columns are
 * just dead weight.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ugc_creator_videos')) {
            return;
        }

        if (! Schema::hasColumn('ugc_creator_videos', 'video_details')) {
            Schema::table('ugc_creator_videos', function (Blueprint $table) {
                $table->text('video_details')->nullable()->after('camera_preset');
            });
        }

        foreach (['script_dialogue', 'script_voiceover', 'script_actions', 'script_expressions', 'script_camera'] as $column) {
            if (Schema::hasColumn('ugc_creator_videos', $column)) {
                Schema::table('ugc_creator_videos', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ugc_creator_videos')) {
            return;
        }

        Schema::table('ugc_creator_videos', function (Blueprint $table) {
            if (! Schema::hasColumn('ugc_creator_videos', 'script_dialogue')) {
                $table->text('script_dialogue')->nullable();
            }
            if (! Schema::hasColumn('ugc_creator_videos', 'script_voiceover')) {
                $table->text('script_voiceover')->nullable();
            }
            if (! Schema::hasColumn('ugc_creator_videos', 'script_actions')) {
                $table->text('script_actions')->nullable();
            }
            if (! Schema::hasColumn('ugc_creator_videos', 'script_expressions')) {
                $table->text('script_expressions')->nullable();
            }
            if (! Schema::hasColumn('ugc_creator_videos', 'script_camera')) {
                $table->text('script_camera')->nullable();
            }
            if (Schema::hasColumn('ugc_creator_videos', 'video_details')) {
                $table->dropColumn('video_details');
            }
        });
    }
};
