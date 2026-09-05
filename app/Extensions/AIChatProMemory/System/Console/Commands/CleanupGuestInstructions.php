<?php

namespace App\Extensions\AIChatProMemory\System\Console\Commands;

use App\Extensions\AIChatProMemory\System\Models\UserChatInstruction;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CleanupGuestInstructions extends Command
{
    protected $signature = 'ai-memory:cleanup';

    protected $description = 'Clean up old guest chat instructions (90+ days)';

    public function handle(): int
    {
        $this->info('Cleaning up old guest instructions...');

        $deleted = UserChatInstruction::cleanupOldGuest();

        $this->info("Cleanup completed! Deleted {$deleted} old guest instructions.");

        return CommandAlias::SUCCESS;
    }
}
