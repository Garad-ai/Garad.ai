<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPLACEMENTS = [
        'claude-3-7-sonnet-20250219' => 'Claude Sonnet 5.5',
        'claude-3-5-sonnet-20241022' => 'Claude Sonnet 5.5',
        'claude-3-5-sonnet-20240620' => 'Claude Sonnet 5.5',
        'claude-3-sonnet-20240229'   => 'Claude Sonnet 5.5',
        'claude-sonnet-4-20250514'  => 'Claude Sonnet 5.5',
        'claude-3-opus-20240229'    => 'Claude Opus 4.8',
        'claude-opus-4-1-20250805'  => 'Claude Opus 4.8',
        'claude-opus-4-20250514'    => 'Claude Opus 4.8',
        'claude-2.0'                => 'Claude Opus 4.8',
        'claude-2.1'                => 'Claude Opus 4.8',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('entities') || ! Schema::hasColumn('entities', 'selected_title')) {
            return;
        }

        foreach (self::REPLACEMENTS as $key => $replacement) {
            $entity = DB::table('entities')->where('key', $key)->first(['id', 'title', 'selected_title']);
            if ($entity === null) {
                continue;
            }

            $selectedTitle = trim((string) ($entity->selected_title ?: $entity->title));
            $suffix = ' (routes to ' . $replacement . ')';
            if (! str_ends_with($selectedTitle, $suffix)) {
                DB::table('entities')->where('id', $entity->id)->update([
                    'selected_title' => $selectedTitle . $suffix,
                    'updated_at'     => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('entities') || ! Schema::hasColumn('entities', 'selected_title')) {
            return;
        }

        foreach (self::REPLACEMENTS as $key => $replacement) {
            $entity = DB::table('entities')->where('key', $key)->first(['id', 'selected_title']);
            if ($entity === null) {
                continue;
            }

            $selectedTitle = (string) $entity->selected_title;
            $suffix = ' (routes to ' . $replacement . ')';
            if (str_ends_with($selectedTitle, $suffix)) {
                DB::table('entities')->where('id', $entity->id)->update([
                    'selected_title' => substr($selectedTitle, 0, -strlen($suffix)),
                    'updated_at'     => now(),
                ]);
            }
        }
    }
};
