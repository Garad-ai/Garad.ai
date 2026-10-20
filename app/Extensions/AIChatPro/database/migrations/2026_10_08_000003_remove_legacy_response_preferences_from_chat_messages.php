<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MESSAGE_TABLE = 'user_openai_chat_messages';

    private const BACKUP_TABLE = 'ai_chat_pro_legacy_message_input_backups';

    public function up(): void
    {
        if (! Schema::hasTable(self::MESSAGE_TABLE)) {
            return;
        }

        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            Schema::create(self::BACKUP_TABLE, function (Blueprint $table): void {
                $table->unsignedBigInteger('message_id')->primary();
                $table->longText('original_input');
                $table->longText('normalized_input');
            });
        }

        DB::table(self::MESSAGE_TABLE)
            ->whereNotNull('input')
            ->where('input', 'like', '%Please follow these response preferences:%')
            ->orderBy('id')
            ->chunkById(500, function ($messages): void {
                foreach ($messages as $message) {
                    [$normalizedInput, $instruction] = $this->separateLegacyPreferences($message->input);
                    if ($instruction === null) {
                        continue;
                    }

                    DB::table(self::BACKUP_TABLE)->insertOrIgnore([
                        'message_id' => $message->id,
                        'original_input' => $message->input,
                        'normalized_input' => $normalizedInput,
                    ]);
                    DB::table(self::MESSAGE_TABLE)
                        ->where('id', $message->id)
                        ->where('input', $message->input)
                        ->update(['input' => $normalizedInput]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }

        if (Schema::hasTable(self::MESSAGE_TABLE)) {
            DB::table(self::BACKUP_TABLE)
                ->orderBy('message_id')
                ->chunkById(500, function ($backups): void {
                    foreach ($backups as $backup) {
                        $message = DB::table(self::MESSAGE_TABLE)
                            ->select('id', 'input')
                            ->where('id', $backup->message_id)
                            ->first();

                        if ($message && $message->input === $backup->normalized_input) {
                            DB::table(self::MESSAGE_TABLE)
                                ->where('id', $backup->message_id)
                                ->update(['input' => $backup->original_input]);
                        }
                    }
                }, 'message_id');
        }

        Schema::dropIfExists(self::BACKUP_TABLE);
    }

    private function separateLegacyPreferences(string $input): array
    {
        $marker = "\n\nPlease follow these response preferences:\n";
        $markerPosition = strrpos($input, $marker);
        if ($markerPosition === false) {
            return [$input, null];
        }

        $generatedPreferences = substr($input, $markerPosition + strlen($marker));
        $lines = explode("\n", $generatedPreferences);
        if (
            ! preg_match('/^- Reply in (?:English|Somali)\.$/D', $lines[0] ?? '')
            || collect($lines)->contains(fn (string $line): bool => $line === '' || ! str_starts_with($line, '- '))
        ) {
            return [$input, null];
        }

        return [substr($input, 0, $markerPosition), $generatedPreferences];
    }
};
