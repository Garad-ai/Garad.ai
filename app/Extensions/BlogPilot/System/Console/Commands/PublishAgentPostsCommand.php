<?php

namespace App\Extensions\BlogPilot\System\Console\Commands;

use App\Extensions\BlogPilot\System\Http\Controllers\BlogPilotController;
use App\Extensions\BlogPilot\System\Models\BlogPilotPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class PublishAgentPostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blogpilot:publish-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish generated posts with integrations (Wordpress)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting BlogPilot post publishing...');
        $this->newLine();

        Log::info('blogpilot:publish-posts started');

        $controller = new BlogPilotController;
        $counter = 0;

        BlogPilotPost::query()
            ->readyToPublish()
            ->whereNull('published_at')
            ->each(function ($post) use ($controller, &$counter) {
                $controller->publishPost($post->id, $post->user_id);
                $counter++;
            });

        if ($counter > 0) {
            $this->info("  ✓ Published {$counter} posts");
        } else {
            $this->line('  • Buffer sufficient, no posts published');
        }
        $this->newLine();
        $this->info('✅ Publih posts completed!');
        Log::info('blogpilot:publish-posts finished');

        return CommandAlias::SUCCESS;
    }
}
