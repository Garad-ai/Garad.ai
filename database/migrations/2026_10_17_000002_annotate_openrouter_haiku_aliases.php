<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LABELS = [
        'anthropic/claude-3-5-haiku-20241022'      => ' (routes to Anthropic Haiku 4.5 via OpenRouter)',
        'anthropic/claude-3-5-haiku-20241022:beta' => ' (routes to standard Haiku 4.5; self-moderated beta unavailable)',
        'anthropic/claude-3-5-haiku'               => ' (routes to Anthropic Haiku 4.5 via OpenRouter)',
        'anthropic/claude-3-5-haiku:beta'          => ' (routes to standard Haiku 4.5; self-moderated beta unavailable)',
    ];

    public function up(): void
    {
        $this->updateLabels(true);
    }

    public function down(): void
    {
        $this->updateLabels(false);
    }

    private function updateLabels(bool $append): void
    {
        if (! Schema::hasTable('entities') || ! Schema::hasColumn('entities', 'selected_title')) {
            return;
        }

        foreach (self::LABELS as $key => $suffix) {
            $entity = DB::table('entities')->where('key', $key)->first(['id', 'title', 'selected_title']);
            if ($entity === null) {
                continue;
            }

            $selectedTitle = trim((string) ($entity->selected_title ?: $entity->title));
            if ($append && ! str_ends_with($selectedTitle, $suffix)) {
                $selectedTitle .= $suffix;
            } elseif (! $append && str_ends_with($selectedTitle, $suffix)) {
                $selectedTitle = substr($selectedTitle, 0, -strlen($suffix));
            } else {
                continue;
            }

            DB::table('entities')->where('id', $entity->id)->update([
                'selected_title' => $selectedTitle,
                'updated_at'     => now(),
            ]);
        }
    }
};
