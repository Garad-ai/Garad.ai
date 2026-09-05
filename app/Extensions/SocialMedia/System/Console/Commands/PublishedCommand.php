<?php

namespace App\Extensions\SocialMedia\System\Console\Commands;

use App\Extensions\SocialMedia\System\Enums\StatusEnum;
use App\Extensions\SocialMedia\System\Models\SocialMediaPost;
use App\Extensions\SocialMedia\System\Services\Publisher\Contracts\BasePublisherService;
use App\Extensions\SocialMedia\System\Services\Publisher\PublisherDriver;
use Illuminate\Console\Command;

class PublishedCommand extends Command
{
    protected $signature = 'app:social-media-published-command';

    protected $description = 'Publish scheduled social media posts';

    public function handle(): void
    {
        $posts = SocialMediaPost::query()
            ->with('platform')
            ->where('status', StatusEnum::scheduled->value)
            ->where('scheduled_at', '<', now())
            ->get();

        $service = app(PublisherDriver::class);

        foreach ($posts as $post) {
            $driver = $service
                ->setPost($post)
                ->getDriver();

            if ($driver instanceof BasePublisherService) {
                $driver->publish();
            }
        }
    }
}
