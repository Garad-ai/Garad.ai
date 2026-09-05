<?php

use App\Models\OpenaiGeneratorChatCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Move the CRM assistant off its bespoke `crm_ai_conversations` table and onto the
     * core chat tables (`user_openai_chat` / `user_openai_chat_messages`) using the
     * `crm-assistant` chat_type discriminator, mirroring the Social Media Agent chat.
     *
     * The source table is intentionally left in place (flagged via `migrated_at`) so the
     * conversion stays idempotent and reversible.
     */
    public function up(): void
    {
        if (! Schema::hasTable('crm_ai_conversations')) {
            return;
        }

        if (! Schema::hasColumn('crm_ai_conversations', 'migrated_at')) {
            Schema::table('crm_ai_conversations', function (Blueprint $table) {
                $table->timestamp('migrated_at')->nullable()->after('messages');
            });
        }

        $categoryId = $this->resolveCategoryId();

        DB::table('crm_ai_conversations')
            ->whereNull('migrated_at')
            ->orderBy('id')
            ->chunkById(200, function ($conversations) use ($categoryId) {
                foreach ($conversations as $conversation) {
                    DB::transaction(function () use ($conversation, $categoryId) {
                        $this->convert($conversation, $categoryId);
                    });
                }
            });
    }

    public function down(): void
    {
        DB::table('user_openai_chat')->where('chat_type', 'crm-assistant')->delete();

        if (Schema::hasColumn('crm_ai_conversations', 'migrated_at')) {
            DB::table('crm_ai_conversations')->update(['migrated_at' => null]);
        }
    }

    private function convert(object $conversation, ?int $categoryId): void
    {
        $chatId = DB::table('user_openai_chat')->insertGetId([
            'user_id'                 => $conversation->user_id,
            'openai_chat_category_id' => $categoryId,
            'chat_type'               => 'crm-assistant',
            'title'                   => $conversation->title,
            'total_credits'           => 0,
            'total_words'             => 0,
            'created_at'              => $conversation->created_at,
            'updated_at'              => $conversation->updated_at,
        ]);

        $rows = [];

        foreach ($this->pairs($conversation->messages) as $pair) {
            $rows[] = [
                'user_openai_chat_id' => $chatId,
                'user_id'             => $conversation->user_id,
                'input'               => $pair['input'],
                'response'            => null,
                'output'              => $pair['output'],
                'hash'                => Str::random(256),
                'credits'             => 0,
                'words'               => 0,
                'created_at'          => $conversation->created_at,
                'updated_at'          => $conversation->updated_at,
            ];
        }

        if ($rows !== []) {
            DB::table('user_openai_chat_messages')->insert($rows);
        }

        DB::table('crm_ai_conversations')
            ->where('id', $conversation->id)
            ->update(['migrated_at' => now()]);
    }

    /**
     * Collapse the flat [{role, content}, ...] transcript into the core schema's
     * one-row-per-exchange shape.
     *
     * @return array<int, array{input: ?string, output: ?string}>
     */
    private function pairs(?string $messages): array
    {
        $decoded = json_decode((string) $messages, true);

        if (! is_array($decoded)) {
            return [];
        }

        $pairs = [];
        $pending = null;

        foreach ($decoded as $message) {
            $role = $message['role'] ?? null;
            $content = $message['content'] ?? null;

            if ($role === 'user') {
                if ($pending !== null) {
                    $pairs[] = ['input' => $pending, 'output' => null];
                }

                $pending = $content;

                continue;
            }

            if ($role === 'assistant') {
                $pairs[] = ['input' => $pending, 'output' => $content];
                $pending = null;
            }
        }

        if ($pending !== null) {
            $pairs[] = ['input' => $pending, 'output' => null];
        }

        return $pairs;
    }

    private function resolveCategoryId(): ?int
    {
        return OpenaiGeneratorChatCategory::query()
            ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
            ->where('role', 'default')
            ->value('id')
            ?? OpenaiGeneratorChatCategory::query()
                ->whereNotIn('slug', ['ai_vision', 'ai_webchat', 'ai_pdf'])
                ->value('id');
    }
};
