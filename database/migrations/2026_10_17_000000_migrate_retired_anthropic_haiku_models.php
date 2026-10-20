<?php

use App\Models\Setting;
use App\Models\SettingTwo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const RETIRED_KEYS = [
        'claude-3-haiku-20240307',
        'claude-3-haiku-20241022',
    ];

    private const REPLACEMENT_KEY = 'claude-haiku-4-5-20251001';

    public function up(): void
    {
        $retiredEntities = DB::table('entities')
            ->whereIn('key', self::RETIRED_KEYS)
            ->orderByRaw("CASE WHEN `key` = 'claude-3-haiku-20241022' THEN 0 ELSE 1 END")
            ->get();

        if ($retiredEntities->isEmpty()) {
            return;
        }

        if (DB::table('entities')->where('key', self::REPLACEMENT_KEY)->exists()) {
            throw new \RuntimeException('Claude Haiku 4.5 already exists; migrate its access and billing records explicitly before retrying.');
        }

        $primary = $retiredEntities->first();
        $primaryId = (int) $primary->id;
        $hasSelectedAccess = $retiredEntities->contains(static fn ($entity): bool => (bool) $entity->is_selected);
        $hasEnabledEntity = $retiredEntities->contains(static fn ($entity): bool => $entity->status === 'enabled');

        DB::table('entities')->where('id', $primaryId)->update([
            'key'           => self::REPLACEMENT_KEY,
            'engine'        => 'anthropic',
            'title'         => 'Claude Haiku 4.5',
            'selected_title'=> 'Claude Haiku 4.5',
            'status'        => $hasEnabledEntity ? 'enabled' : $primary->status,
            'is_selected'   => $hasSelectedAccess ? 1 : 0,
            'updated_at'    => now(),
        ]);

        foreach ($retiredEntities->skip(1) as $entity) {
            $this->copyPlanAccess((int) $entity->id, $primaryId);
            $this->copyTokenTypes((int) $entity->id, $primaryId);

            DB::table('entities')->where('id', $entity->id)->update([
                'status'     => 'disabled',
                'updated_at' => now(),
            ]);
        }

        $this->replaceSavedDefaults(self::RETIRED_KEYS, self::REPLACEMENT_KEY);
        $this->replaceAppSetting(
            'anthropic_bedrock_model',
            ['anthropic.claude-3-sonnet-20240229-v1:0'],
            'anthropic.claude-sonnet-5-5',
        );
        $this->forgetModelCaches();
    }

    public function down(): void
    {
        $restoreKey = DB::table('entities')
            ->where('key', 'claude-3-haiku-20240307')
            ->exists()
                ? 'claude-3-haiku-20241022'
                : 'claude-3-haiku-20240307';

        DB::table('entities')
            ->where('key', self::REPLACEMENT_KEY)
            ->update([
                'key'           => $restoreKey,
                'engine'        => 'anthropic',
                'title'         => 'Claude 3.5 Haiku',
                'selected_title'=> 'Claude 3.5 Haiku',
                'updated_at'    => now(),
            ]);

        DB::table('entities')
            ->where('key', 'claude-3-haiku-20240307')
            ->update([
                'status'     => 'enabled',
                'updated_at' => now(),
            ]);

        $this->replaceSavedDefaults([self::REPLACEMENT_KEY], 'claude-3-haiku-20241022');
        $this->replaceAppSetting(
            'anthropic_bedrock_model',
            ['anthropic.claude-sonnet-5-5'],
            'anthropic.claude-3-sonnet-20240229-v1:0',
        );
        $this->forgetModelCaches();
    }

    private function copyPlanAccess(int $sourceId, int $replacementId): void
    {
        foreach (DB::table('ai_chat_model_plans')->where('entity_id', $sourceId)->pluck('plan_id') as $planId) {
            $alreadyLinked = DB::table('ai_chat_model_plans')
                ->where('entity_id', $replacementId)
                ->where('plan_id', $planId)
                ->exists();

            if (! $alreadyLinked) {
                DB::table('ai_chat_model_plans')->insert([
                    'entity_id' => $replacementId,
                    'plan_id'   => $planId,
                ]);
            }
        }
    }

    private function copyTokenTypes(int $sourceId, int $replacementId): void
    {
        foreach (DB::table('tokens')->where('entity_id', $sourceId)->get(['type']) as $token) {
            $alreadyExists = DB::table('tokens')
                ->where('entity_id', $replacementId)
                ->where('type', $token->type)
                ->exists();

            if (! $alreadyExists) {
                DB::table('tokens')->insert([
                    'entity_id'  => $replacementId,
                    'type'       => $token->type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function replaceSavedDefaults(array $oldKeys, string $replacementKey): void
    {
        $this->replaceAppSetting('anthropic_default_model', $oldKeys, $replacementKey);

        foreach (['settings', 'settings_two', 'users', 'user_settings', 'user_preferences'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'anthropic_default_model')) {
                continue;
            }

            DB::table($table)
                ->whereIn('anthropic_default_model', $oldKeys)
                ->update(['anthropic_default_model' => $replacementKey]);
        }
    }

    private function replaceAppSetting(string $key, array $oldValues, string $replacementValue): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')
            ->where('key', $key)
            ->whereIn('value', $oldValues)
            ->update(['value' => $replacementValue]);

        app('setting')->load(true);
    }

    private function forgetModelCaches(): void
    {
        Cache::forget('entities');
        Setting::forgetCache();
        SettingTwo::forgetCache();
    }
};
